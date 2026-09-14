<?php

declare(strict_types=1);

namespace Tests\Domain\DNS;

use GuzzleHttp\Psr7\Response;
use Illuminate\Contracts\Bus\Dispatcher;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Rfc4122\UuidV4;
use SandwaveIo\LighthouseAuthBase\Enum\SchemaId;
use SandwaveIo\LighthouseAuthBase\Identity\KratosIdentity;
use SandwaveIo\LighthouseAuthBase\Identity\Traits;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Infra\PowerDnsClient\PowerDnsMockHelper;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\Entities\AddedDnsRecord;
use Waterfront\Domain\DNS\Entities\ChangedDnsRecord;
use Waterfront\Domain\DNS\Entities\DnsRecords\AbstractRecord;
use Waterfront\Domain\DNS\Entities\DnsRecords\ARecord;
use Waterfront\Domain\DNS\Entities\DnsRecords\CnameRecord;
use Waterfront\Domain\DNS\Entities\DnsRecords\DefaultRecord;
use Waterfront\Domain\DNS\Entities\DnsZone;
use Waterfront\Domain\DNS\Entities\DnsZoneDiff;
use Waterfront\Domain\DNS\Enums\DnsChangeType;
use Waterfront\Domain\DNS\Enums\DnsRecordType;
use Waterfront\Domain\DNS\Exceptions\DnsZoneAlreadyCreatedException;
use Waterfront\Domain\DNS\Exceptions\DnsZoneNotFoundException;
use Waterfront\Domain\DNS\Hydrators\DnsRecordHydrator;
use Waterfront\Domain\DNS\Interfaces\DnsRecordInterface;
use Waterfront\Domain\DNS\Interfaces\DnsZoneFactoryInterface;
use Waterfront\Domain\DNS\Jobs\UpdateNameservers;
use Waterfront\Domain\DNS\Models\DnsCustomerTemplate;
use Waterfront\Domain\DNS\Models\DnsCustomerTemplateRecord;
use Waterfront\Domain\DNS\Models\DnsRecordChange;
use Waterfront\Domain\DNS\Repository\DnsRecordChangeRepository;
use Waterfront\Domain\DNS\Services\DnsLogService;
use Waterfront\Domain\DNS\Services\DnsRecordConverter;
use Waterfront\Domain\DNS\Services\DnsZoneService;
use Waterfront\Domain\DNS\ValueObjects\Fqdn;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Infra\Authentication\AuthenticationManager;
use Waterfront\Infra\Authentication\DTO\AuthenticatedCustomer;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\PowerDnsClient\Entities\PowerDnsSecKeySet;
use Waterfront\Infra\PowerDnsClient\Enums\PowerDnsMetadataType;
use Waterfront\Infra\PowerDnsClient\Enums\PowerDnsZoneKind;
use Waterfront\Infra\PowerDnsClient\PowerDnsClient;
use Waterfront\Infra\PowerDnsClient\Services\PowerDnsSoaSerialUpdater;
use Waterfront\Support\Helpers\SystemHelper;

#[CoversClass(DnsService::class)]
#[AllowMockObjectsWithoutExpectations]
class DnsServiceTest extends IntegrationTestCase
{
    use PowerDnsMockHelper;

    private const string DOMAIN = 'test.com';

    private PowerDnsClient&MockObject $mockPowerDnsClient;

    private DnsZone $dnsZone;

    private DnsService $dnsService;

    private DnsZoneService&MockObject $mockDnsZoneService;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->createOne();

        new SubscriptionFactory()
            ->for($this->customer)
            ->for(new ProductFactory()->premiumDns())
            ->forDomain(self::DOMAIN)
            ->administrativeStatusActive()
            ->technicalStatusOk()
            ->createOne();

        $this->mockPowerDnsClient = self::createMock(PowerDnsClient::class);

        $this->dnsZone = new DnsZone(new Fqdn(self::DOMAIN));

        $dnsRecordChangeRepository = self::resolve(DnsRecordChangeRepository::class);
        $subscriptionRepository = self::resolve(SubscriptionRepository::class);

        $identitySchema = new KratosIdentity(
            UuidV4::uuid4(),
            SchemaId::CUSTOMER,
            'active',
            null,
            new Traits('pieter@post.nl', null),
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
        );
        $customer = new AuthenticatedCustomer(
            $this->customer,
            $identitySchema,
            true,
        );

        $authManager = self::createMock(AuthenticationManager::class);
        $authManager->method('getAuthenticatedSubject')->willReturn($customer);

        $systemHelper = self::createMock(SystemHelper::class);
        $systemHelper->method('getClientIp')->willReturn('1.1.1.1');

        $dnsLogService = new DnsLogService(
            $dnsRecordChangeRepository,
            $subscriptionRepository,
            $systemHelper,
            $authManager,
        );

        $this->mockDnsZoneService = self::createMock(DnsZoneService::class);

        $this->dnsService = new DnsService(
            powerDnsClient: $this->mockPowerDnsClient,
            dnsZoneFactory: self::resolve(DnsZoneFactoryInterface::class),
            configuration: self::resolve(ConfigurationInterface::class),
            jobDispatcher: self::resolve(Dispatcher::class),
            logger: self::resolve(LoggerInterface::class),
            hydrator: self::resolve(DnsRecordHydrator::class),
            dnsLogService: $dnsLogService,
            dnsRecordConverter: self::resolve(DnsRecordConverter::class),
            dnsZoneService: $this->mockDnsZoneService,
        );
    }

    #[Test]
    public function addAndUpdateDnsRecord(): void
    {
        $cname = [
            [
                'comments' => [],
                'name' => 'cname-example.test.nl.',
                'records' => [
                    [
                        'content' => 'pieters-super-server.test.nl',
                        'disabled' => false,
                        'ttl' => 1200,
                        'name' => 'cname-example.test.nl',
                        'type' => 'CNAME',
                    ],
                ],
                'ttl' => 1200,
                'type' => 'CNAME',
            ],
        ];

        $pdns = $this->makePdnsWithMultipleResponses([
            new Response(
                201,
                [],
                $this->getMockedZoneResponseBody(self::DOMAIN),
            ),
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody(self::DOMAIN),
            ),
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody(self::DOMAIN),
            ),
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody(self::DOMAIN, $cname),
            ),
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody(self::DOMAIN, $cname),
            ),
            new Response(
                200,
                [],
            ),
        ]);

        $this->pdns($pdns);

        $dnsService = self::resolve(DnsService::class);
        $dnsZone = $dnsService->createDnsZone(self::DOMAIN, 'default');

        $records = $dnsZone->getRecords();

        $record = new CnameRecord('cname-example.test.nl.', 'pieters-super-server.test.nl.', 12000);
        $expectedRecords = $records;
        $expectedRecords[] = $record;
        foreach ($expectedRecords as $key => $expectedRecord) {
            if ($expectedRecord->getType() !== 'SOA') {
                continue;
            }

            $expectedRecords[$key] = new DefaultRecord(
                type: $expectedRecord->getType(),
                name: $expectedRecord->getName(),
                content: PowerDnsSoaSerialUpdater::increaseSoaSerial($expectedRecord->getContent()),
                ttl: $expectedRecord->getTtl() ?? 3600,
                disabled: $expectedRecord->isDisabled(),
            );
        }

        $dnsZone = $dnsService->addDnsRecord(self::DOMAIN, $record);

        self::assertSame(
            json_encode($expectedRecords, JSON_THROW_ON_ERROR),
            json_encode($dnsZone->getRecords(), JSON_THROW_ON_ERROR),
        );

        $new = new CnameRecord('cname-example.test.nl.', 'pieters-super-server.test.nl.', 1200);
        $change = new ChangedDnsRecord($record, $new);
        $expectedRecords[count($expectedRecords) - 1] = $new;

        $dnsZone = $dnsService->updateRecord(self::DOMAIN, $change);

        foreach ($expectedRecords as $key => $expectedRecord) {
            if ($expectedRecord->getType() === 'SOA') {
                $expectedRecords[$key] = new DefaultRecord(
                    type: $expectedRecord->getType(),
                    name: $expectedRecord->getName(),
                    content: PowerDnsSoaSerialUpdater::increaseSoaSerial($expectedRecord->getContent()),
                    ttl: $expectedRecord->getTtl() ?? 3600,
                    disabled: $expectedRecord->isDisabled(),
                );
            }
        }

        self::assertSame(
            json_encode($expectedRecords, JSON_THROW_ON_ERROR),
            json_encode($dnsZone->getRecords(), JSON_THROW_ON_ERROR),
        );
    }

    #[Test]
    public function removeDnsRecord(): void
    {
        $pdns = $this->makePdnsWithMultipleResponses([
            new Response(
                201,
                [],
                $this->getMockedZoneResponseBody(self::DOMAIN),
            ),
            new Response(
                201,
                [],
                $this->getMockedZoneResponseBody(self::DOMAIN),
            ),
            new Response(
                204,
                [],
            ),
        ]);

        $this->pdns($pdns);

        $dnsService = self::resolve(DnsService::class);
        $dnsZone = $dnsService->createDnsZone(self::DOMAIN, 'default');
        $records = $dnsZone->getRecords();
        $dnsZone = $dnsService->removeDnsRecord(self::DOMAIN, $records[0]);

        $expectedCount = count($records) - 1;
        self::assertCount($expectedCount, $dnsZone->getRecords());

        // It records 6 dns records, the SOA is ignored
        self::assertSame($expectedCount, DnsRecordChange::where('change_type', DnsChangeType::CREATED)->count());
        // It deleted 1 dns record
        self::assertSame(1, DnsRecordChange::where('change_type', DnsChangeType::DELETED)->count());
    }

    #[Test]
    public function enablePremiumDns(): void
    {
        $testDomain = 'test-premium-dns.nl';
        $testIp = '::1';

        $mockPowerDnsClient = self::mock(PowerDnsClient::class);
        $mockDnsZoneFactory = self::createMock(DnsZoneFactoryInterface::class);
        $mockConfiguration = self::createMock(ConfigurationInterface::class);
        $mockJobDispatcher = self::createMock(Dispatcher::class);
        $mockLoggerInterface = self::createMock(LoggerInterface::class);
        $dnsRecordHydrator = self::resolve(DnsRecordHydrator::class);
        $mockDnsLogService = self::createMock(DnsLogService::class);

        $service = new DnsService(
            powerDnsClient: $mockPowerDnsClient,
            dnsZoneFactory: $mockDnsZoneFactory,
            configuration: $mockConfiguration,
            jobDispatcher: $mockJobDispatcher,
            logger: $mockLoggerInterface,
            hydrator: $dnsRecordHydrator,
            dnsLogService: $mockDnsLogService,
            dnsRecordConverter: self::resolve(DnsRecordConverter::class),
            dnsZoneService: self::createMock(DnsZoneService::class),
        );

        // Zone contains a record with a TTL below the premium minimum (300) and
        // one that is already compliant. ensureMinimumTTL() should raise the low
        // TTL record via getZone()/changeZone() before the premium metadata is set.
        $lowTtlRecord = new ARecord('low-ttl', '127.0.0.1', 60);
        $highTtlRecord = new ARecord('high-ttl', '127.0.0.2', 3600);
        $existingZone = new DnsZone(new Fqdn($testDomain));
        $existingZone->setRecords([$lowTtlRecord, $highTtlRecord]);

        $mockPowerDnsClient->shouldReceive('getZone')->once()->with($testDomain)->andReturn($existingZone);

        $mockPowerDnsClient
            ->shouldReceive('changeZone')
            ->once()
            ->withArgs(function (DnsZone $zone) use ($lowTtlRecord, $highTtlRecord): bool {
                self::assertCount(2, $zone->getRecords());
                $changedTtl = $zone->getRecords()[0];
                $unchangedTtl = $zone->getRecords()[1];

                return (
                    $changedTtl->getType() === DnsRecordType::A->value
                    && $changedTtl->getName() === $lowTtlRecord->getName()
                    && $changedTtl->getContent() === $lowTtlRecord->getContent()
                    && $changedTtl->getTtl() === 300
                    && $unchangedTtl->getType() === DnsRecordType::A->value
                    && $unchangedTtl->getName() === $highTtlRecord->getName()
                    && $unchangedTtl->getContent() === $highTtlRecord->getContent()
                    && $unchangedTtl->getTtl() === $highTtlRecord->getTtl()
                );
            })
            ->andReturnUsing(fn (DnsZone $zone): DnsZone => $zone);

        $mockConfiguration
            ->expects(self::once())
            ->method('getAsBoolean')
            ->with('dns.gandi.live_dns_notify_bridge.use_ipv6')
            ->willReturn(true);

        $mockConfiguration
            ->expects(self::once())
            ->method('getAsString')
            ->with('dns.gandi.live_dns_notify_bridge.ipv6')
            ->willReturn($testIp);

        $mockPowerDnsClient
            ->shouldReceive('createMetadata')
            ->once()
            ->with($testDomain, PowerDnsMetadataType::ALLOW_AXFR_FROM, [$testIp]);

        $mockPowerDnsClient
            ->shouldReceive('createMetadata')
            ->once()
            ->with($testDomain, PowerDnsMetadataType::ALSO_NOTIFY, [$testIp]);

        $mockPowerDnsClient
            ->shouldReceive('createMetadata')
            ->once()
            ->with($testDomain, PowerDnsMetadataType::SOA_EDIT, ['INCEPTION-INCREMENT']);

        $mockPowerDnsClient->shouldReceive('updateLiveDns')->once()->with($testDomain, true);

        $mockPowerDnsClient->shouldReceive('sendNotify')->once()->with($testDomain);

        $mockJobDispatcher->expects(self::once())->method('dispatch');

        $service->enablePremiumDns($testDomain);
    }

    #[Test]
    public function disablePremiumDNS(): void
    {
        $testDomain = 'test-premium-dns.nl';

        $mockPowerDnsClient = self::mock(PowerDnsClient::class);
        $mockDnsZoneFactory = self::createMock(DnsZoneFactoryInterface::class);
        $mockConfiguration = self::createMock(ConfigurationInterface::class);
        $mockJobDispatcher = self::createMock(Dispatcher::class);
        $mockLoggerInterface = self::createMock(LoggerInterface::class);
        $dnsRecordHydrator = self::resolve(DnsRecordHydrator::class);
        $mockDnsLogService = self::createMock(DnsLogService::class);

        $service = new DnsService(
            powerDnsClient: $mockPowerDnsClient,
            dnsZoneFactory: $mockDnsZoneFactory,
            configuration: $mockConfiguration,
            jobDispatcher: $mockJobDispatcher,
            logger: $mockLoggerInterface,
            hydrator: $dnsRecordHydrator,
            dnsLogService: $mockDnsLogService,
            dnsRecordConverter: self::resolve(DnsRecordConverter::class),
            dnsZoneService: self::createMock(DnsZoneService::class),
        );

        $mockPowerDnsClient
            ->shouldReceive('deleteMetadata')
            ->once()
            ->with($testDomain, PowerDnsMetadataType::ALLOW_AXFR_FROM);

        $mockPowerDnsClient
            ->shouldReceive('deleteMetadata')
            ->once()
            ->with($testDomain, PowerDnsMetadataType::ALSO_NOTIFY);

        $mockPowerDnsClient->shouldReceive('deleteMetadata')->once()->with($testDomain, PowerDnsMetadataType::SOA_EDIT);

        $mockPowerDnsClient->shouldReceive('updateLiveDns')->once()->with($testDomain, false);

        $mockJobDispatcher->expects(self::once())->method('dispatch');

        $service->disablePremiumDns($testDomain, true);
    }

    #[Test]
    public function disablePremiumDnsWithNameserverUpdate(): void
    {
        $testDomain = 'test-premium-dns.nl';

        $mockPowerDnsClient = self::mock(PowerDnsClient::class);
        $mockDnsZoneFactory = self::createMock(DnsZoneFactoryInterface::class);
        $mockConfiguration = self::createMock(ConfigurationInterface::class);
        $mockJobDispatcher = self::createMock(Dispatcher::class);
        $mockLoggerInterface = self::createMock(LoggerInterface::class);
        $dnsRecordHydrator = self::resolve(DnsRecordHydrator::class);
        $mockDnsLogService = self::createMock(DnsLogService::class);

        $service = new DnsService(
            powerDnsClient: $mockPowerDnsClient,
            dnsZoneFactory: $mockDnsZoneFactory,
            configuration: $mockConfiguration,
            jobDispatcher: $mockJobDispatcher,
            logger: $mockLoggerInterface,
            hydrator: $dnsRecordHydrator,
            dnsLogService: $mockDnsLogService,
            dnsRecordConverter: self::resolve(DnsRecordConverter::class),
            dnsZoneService: self::createMock(DnsZoneService::class),
        );

        $mockPowerDnsClient
            ->shouldReceive('deleteMetadata')
            ->once()
            ->with($testDomain, PowerDnsMetadataType::ALLOW_AXFR_FROM);

        $mockPowerDnsClient
            ->shouldReceive('deleteMetadata')
            ->once()
            ->with($testDomain, PowerDnsMetadataType::ALSO_NOTIFY);

        $mockPowerDnsClient->shouldReceive('deleteMetadata')->once()->with($testDomain, PowerDnsMetadataType::SOA_EDIT);

        $mockPowerDnsClient->shouldReceive('updateLiveDns')->once()->with($testDomain, false);

        $mockJobDispatcher->expects(self::once())->method('dispatch')->with(new UpdateNameservers($testDomain, false));

        $service->disablePremiumDns(domain: $testDomain, shouldUpdateNameservers: true);
    }

    #[Test]
    public function disablePremiumDnsWithoutNameserverUpdate(): void
    {
        $testDomain = 'test-premium-dns.nl';

        $mockPowerDnsClient = self::mock(PowerDnsClient::class);
        $mockDnsZoneFactory = self::createMock(DnsZoneFactoryInterface::class);
        $mockConfiguration = self::createMock(ConfigurationInterface::class);
        $mockJobDispatcher = self::createMock(Dispatcher::class);
        $mockLoggerInterface = self::createMock(LoggerInterface::class);
        $dnsRecordHydrator = self::resolve(DnsRecordHydrator::class);
        $mockDnsLogService = self::createMock(DnsLogService::class);

        $service = new DnsService(
            powerDnsClient: $mockPowerDnsClient,
            dnsZoneFactory: $mockDnsZoneFactory,
            configuration: $mockConfiguration,
            jobDispatcher: $mockJobDispatcher,
            logger: $mockLoggerInterface,
            hydrator: $dnsRecordHydrator,
            dnsLogService: $mockDnsLogService,
            dnsRecordConverter: self::resolve(DnsRecordConverter::class),
            dnsZoneService: self::createMock(DnsZoneService::class),
        );

        $mockPowerDnsClient
            ->shouldReceive('deleteMetadata')
            ->once()
            ->with($testDomain, PowerDnsMetadataType::ALLOW_AXFR_FROM);

        $mockPowerDnsClient
            ->shouldReceive('deleteMetadata')
            ->once()
            ->with($testDomain, PowerDnsMetadataType::ALSO_NOTIFY);

        $mockPowerDnsClient->shouldReceive('deleteMetadata')->once()->with($testDomain, PowerDnsMetadataType::SOA_EDIT);

        $mockPowerDnsClient->shouldReceive('updateLiveDns')->once()->with($testDomain, false);

        $mockJobDispatcher->expects(self::never())->method('dispatch');

        $service->disablePremiumDns(domain: $testDomain, shouldUpdateNameservers: false);
    }

    #[Test]
    public function getDnsZoneNotFound(): void
    {
        $pdns = $this->makePdnsWithMultipleResponses([
            new Response(
                404,
                [],
            ),
        ]);

        $this->pdns($pdns);

        $dnsService = self::resolve(DnsService::class);
        $this->expectException(DnsZoneNotFoundException::class);
        $dnsService->getDnsZone(self::DOMAIN);
    }

    #[Test]
    public function updateDnsZoneNotFound(): void
    {
        $pdns = $this->makePdnsWithMultipleResponses([
            new Response(
                404,
                [],
                '{"message" : "zone test.nl does not exist"}',
            ),
        ]);

        $this->pdns($pdns);

        $dnsService = self::resolve(DnsService::class);
        $record = new CnameRecord('cname-example.test.nl.', 'pieters-super-server.test.nl.', 1200);
        $change = new ChangedDnsRecord($record, $record);

        $this->expectException(DnsZoneNotFoundException::class);
        $dnsService->updateRecord(self::DOMAIN, $change);
    }

    #[Test]
    public function zoneAlreadyCreated(): void
    {
        $pdns = $this->makePdnsWithMultipleResponses([
            new Response(
                201,
                [],
                $this->getMockedZoneResponseBody(self::DOMAIN),
            ),
            new Response(
                400,
                [],
                '{"message" : "zone already exists."}',
            ),
        ]);

        $this->pdns($pdns);

        $dnsService = self::resolve(DnsService::class);
        $dnsService->createDnsZone(self::DOMAIN, 'default');
        $this->expectException(DnsZoneAlreadyCreatedException::class);
        $dnsService->createDnsZone(self::DOMAIN, 'default');
    }

    #[Test]
    public function zoneCreate(): void
    {
        $pdns = $this->makePdnsWithMultipleResponses([
            new Response(
                201,
                [],
                $this->getMockedZoneResponseBody(self::DOMAIN),
            ),
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody(self::DOMAIN),
            ),
        ]);

        $this->pdns($pdns);

        $dnsService = self::resolve(DnsService::class);
        $dnsService->createDnsZone(self::DOMAIN);

        $zone = $dnsService->getDnsZone(self::DOMAIN);

        self::assertNotEmpty(
            $zone->getRecords(),
            'Zone did not contain any records.',
        );

        // SOA record is not logged
        self::assertDatabaseCount('dns_record_changes', count($zone->getRecords()) - 1);
    }

    #[Test]
    public function zoneCreateWithDNSSEC(): void
    {
        $pdns = $this->makePdnsWithMultipleResponses([
            new Response(
                201,
                [],
                $this->getMockedZoneResponseBody(self::DOMAIN),
            ),
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody(self::DOMAIN),
            ),
        ]);

        $this->pdns($pdns);

        $dnsService = self::resolve(DnsService::class);
        $dnsService->createDnsZone(
            self::DOMAIN,
            'default',
            null,
            null,
            true,
        );

        $zone = $dnsService->getDnsZone(self::DOMAIN);

        self::assertNotEmpty(
            $zone->getRecords(),
            'Zone did not contain any records.',
        );

        self::assertTrue($zone->hasDnsSec());

        // SOA record is not logged
        self::assertDatabaseCount('dns_record_changes', count($zone->getRecords()) - 1);
    }

    #[Test]
    public function getDnsZoneKey(): void
    {
        $pdns = $this->makePdnsWithMultipleResponses([
            new Response(
                200,
                [],
                $this->getMockedKeyResponseBody(),
            ),
        ]);

        $this->pdns($pdns);

        $dnsService = self::resolve(DnsService::class);
        $key = $dnsService->getDnsZoneKey(self::DOMAIN);

        self::assertSame(
            '257 3 13 kJugvFdAwIy1cLirD3H23rJuf8Ul1XFwponZ7y8qq7rMBN3/Hdvs9PnRTD6Hm4R8sANAh5Cqfn2EZcXvROIxLw==',
            $key->getDnsKey(),
        );
    }

    #[Test]
    public function isSlaveZone(): void
    {
        $this->dnsZone->kind = PowerDnsZoneKind::SLAVE->value;

        $this->mockPowerDnsClient->method('getZone')->willReturn($this->dnsZone);

        $this->app->bind(PowerDnsClient::class, fn (): PowerDnsClient => $this->mockPowerDnsClient);

        self::assertTrue($this->dnsService->isSlaveZone(self::DOMAIN));
    }

    #[Test]
    public function isSlaveZoneMasterProvided(): void
    {
        $this->dnsZone->kind = PowerDnsZoneKind::MASTER->value;

        $this->mockPowerDnsClient->method('getZone')->willReturn($this->dnsZone);

        $this->app->bind(PowerDnsClient::class, fn (): PowerDnsClient => $this->mockPowerDnsClient);

        self::assertFalse($this->dnsService->isSlaveZone(self::DOMAIN));
    }

    #[Test]
    public function applyTemplate(): void
    {
        $template = DnsCustomerTemplate::create([
            'name' => 'exampleExistingTemplate',
            'customer_id' => $this->customer->id,
        ]);

        $existingRecords = [
            new ARecord('a-record', '127.0.0.1', 10),
            new CnameRecord('cname-record', self::DOMAIN, 10),
            new DefaultRecord('NS', self::DOMAIN, 'ns1.test.nl', 10),
        ];

        $templateRecord = new DnsCustomerTemplateRecord([
            'name' => 'template-record-name',
            'content' => 'template-record-content',
            'type' => 'A',
            'ttl' => 100,
            'disabled' => false,
        ]);

        $template->records()->saveMany([$templateRecord]);

        $converter = new DnsRecordConverter();
        $templateTypedRecord = $converter->transformToTypedRecord($templateRecord, self::DOMAIN);

        $zone = new DnsZone(new Fqdn(self::DOMAIN));
        $zone->setRecords($existingRecords);

        $newDnsZone = clone $zone;
        $newDnsZone->removeRecord($existingRecords[0]);
        $newDnsZone->removeRecord($existingRecords[1]);

        $newDnsZone->addRecord($templateTypedRecord);

        $mockDnsLog = self::mock(DnsLogService::class);

        $dnsService = new DnsService(
            powerDnsClient: $this->mockPowerDnsClient,
            dnsZoneFactory: self::resolve(DnsZoneFactoryInterface::class),
            configuration: self::resolve(ConfigurationInterface::class),
            jobDispatcher: self::resolve(Dispatcher::class),
            logger: self::resolve(LoggerInterface::class),
            hydrator: self::resolve(DnsRecordHydrator::class),
            dnsLogService: $mockDnsLog,
            dnsRecordConverter: self::resolve(DnsRecordConverter::class),
            dnsZoneService: self::createMock(DnsZoneService::class),
        );

        $this->mockPowerDnsClient->expects(self::never())->method('getZone');

        $this->mockPowerDnsClient->expects(self::once())->method('changeZone')->willReturn($newDnsZone);

        $mockDnsLog
            ->shouldReceive('logMultipleRecords')
            ->once()
            ->withArgs(
                function (array $recordsDeleted, string $domain, DnsChangeType $changeType) use (
                    $existingRecords,
                    $newDnsZone,
                ) {
                    // we expect the Cname and A record in the deleted log, not the NS record.
                    $aRecordPresent = in_array($existingRecords[0], $recordsDeleted, true);
                    $cnameRecordPresent = in_array($existingRecords[1], $recordsDeleted, true);
                    $nsNotPresent = ! in_array($existingRecords[2], $recordsDeleted, true);

                    return (
                        $domain === $newDnsZone->getFqdn()->toNative()
                        && $changeType === DnsChangeType::DELETED
                        && $aRecordPresent
                        && $cnameRecordPresent
                        && $nsNotPresent
                    );
                },
            );

        $mockDnsLog
            ->shouldReceive('logMultipleRecords')
            ->once()
            ->withArgs(
                /** @var AbstractRecord[] $recordsCreated */
                fn (array $recordsCreated, string $domain, DnsChangeType $changeType) => (
                    // we expect the record from the template to be in the created log and nothing else
                    $domain === $newDnsZone->getFqdn()->toNative()
                    && count($recordsCreated) === 1
                    && $templateTypedRecord->getType() === $recordsCreated[0]->getType()
                    && $templateTypedRecord->getContent() === $recordsCreated[0]->getContent()
                    && $changeType === DnsChangeType::CREATED
                ),
            );

        $dnsService->applyTemplate($zone, self::DOMAIN, $template);
    }

    #[Test]
    public function applyDiff(): void
    {
        $existingRecords = [
            new ARecord('a-record', '127.0.0.1', 10),
            new CnameRecord('cname-record', self::DOMAIN, 10),
            new DefaultRecord('NS', self::DOMAIN, 'ns1.test.nl', 10),
        ];

        $zone = new DnsZone(new Fqdn(self::DOMAIN));
        $zone->setRecords($existingRecords);

        $record = new CnameRecord('new', 'sandwave.io', 10);
        $dnsZoneDiff = new DnsZoneDiff([new AddedDnsRecord($record)]);

        $newDnsZone = new DnsZone(new Fqdn(self::DOMAIN));
        $newDnsZone->setRecords($existingRecords);
        $newDnsZone = $newDnsZone->addRecord($record);

        $this->mockPowerDnsClient->expects(self::once())->method('getZone')->willReturn($zone);

        $this->mockPowerDnsClient->expects(self::once())->method('changeZone')->willReturn($newDnsZone);

        $dnsZone = $this->dnsService->applyDiff(self::DOMAIN, $dnsZoneDiff);

        self::assertCount(4, $dnsZone->getRecords());
        self::assertCount(0, DnsRecordChange::where('change_type', DnsChangeType::DELETED)->get());
        self::assertCount(1, DnsRecordChange::where('change_type', DnsChangeType::CREATED)->get());
    }

    #[Test]
    public function applyDiffReplacesParkingRecordsAndKeepsOtherRecordTypes(): void
    {
        $dkimName = 'default._domainkey.' . self::DOMAIN;

        $zone = new DnsZone(new Fqdn(self::DOMAIN));
        $zone->setRecords([
            new DefaultRecord('A', self::DOMAIN, '203.0.113.1', 600),
            new DefaultRecord('AAAA', self::DOMAIN, '2001:db8::1', 600),
            new DefaultRecord('TXT', self::DOMAIN, '"v=spf1 -all"', 600),
            new DefaultRecord('TXT', $dkimName, '"v=DKIM1; p=abc"', 3600),
            new DefaultRecord('NS', self::DOMAIN, 'ns1.test.nl.', 3600),
        ]);

        $this->mockDnsZoneService
            ->method('getParkingAddressRecords')
            ->willReturn([
                new DefaultRecord('A', self::DOMAIN, '203.0.113.1', 600),
                new DefaultRecord('AAAA', self::DOMAIN, '2001:db8::1', 600),
            ]);

        $dnsZoneDiff = new DnsZoneDiff([
            new AddedDnsRecord(new DefaultRecord('A', self::DOMAIN, '198.51.100.10', 600)),
            new AddedDnsRecord(new DefaultRecord('AAAA', self::DOMAIN, '2001:db8::10', 600)),
        ]);

        $this->mockPowerDnsClient->expects(self::once())->method('getZone')->willReturn($zone);

        $capturedZone = null;
        $this->mockPowerDnsClient
            ->expects(self::once())
            ->method('changeZone')
            ->willReturnCallback(function (DnsZone $dnsZone) use (&$capturedZone): DnsZone {
                $capturedZone = $dnsZone;

                return $dnsZone;
            });

        $this->dnsService->applyDiffReplacingParkingRecords(self::DOMAIN, $dnsZoneDiff);

        self::assertInstanceOf(DnsZone::class, $capturedZone);

        $aRecords = $capturedZone->getRecordsOfTypeAndName('A', self::DOMAIN);
        self::assertCount(1, $aRecords);
        self::assertSame('198.51.100.10', $aRecords[0]->getContent());

        $aaaaRecords = $capturedZone->getRecordsOfTypeAndName('AAAA', self::DOMAIN);
        self::assertCount(1, $aaaaRecords);
        self::assertSame('2001:db8::10', $aaaaRecords[0]->getContent());

        self::assertCount(1, $capturedZone->getRecordsOfTypeAndName('TXT', self::DOMAIN));
        self::assertCount(1, $capturedZone->getRecordsOfTypeAndName('TXT', $dkimName));
        self::assertCount(1, $capturedZone->getRecordsOfTypeAndName('NS', self::DOMAIN));
    }

    #[Test]
    public function getConflictingParkingRecordsReturnsParkingRecordsThatCoexistWithOtherRecords(): void
    {
        $zone = new DnsZone(new Fqdn(self::DOMAIN));
        $zone->setRecords([
            new DefaultRecord('A', self::DOMAIN, '212.204.220.100', 600),
            new DefaultRecord('A', self::DOMAIN, '185.104.29.10', 600),
            new DefaultRecord('AAAA', self::DOMAIN, '2a05:1500:900:2::100', 600),
            new DefaultRecord('AAAA', self::DOMAIN, '2a05:1500:900:3::20', 600),
        ]);

        $this->mockDnsZoneService
            ->method('getParkingAddressRecords')
            ->willReturn([
                new DefaultRecord('A', self::DOMAIN, '212.204.220.100', 600),
                new DefaultRecord('AAAA', self::DOMAIN, '2a05:1500:900:2::100', 600),
            ]);

        $this->mockPowerDnsClient->method('getZone')->willReturn($zone);

        $conflictingRecords = $this->dnsService->getConflictingParkingRecords(self::DOMAIN);

        self::assertCount(2, $conflictingRecords);
        $contents = array_map(fn (DnsRecordInterface $record): string => $record->getContent(), $conflictingRecords);
        self::assertContains('212.204.220.100', $contents);
        self::assertContains('2a05:1500:900:2::100', $contents);
        self::assertNotContains('185.104.29.10', $contents);
    }

    #[Test]
    public function getConflictingParkingRecordsOnlyReturnsRecordsForNamesWithAConflict(): void
    {
        $zone = new DnsZone(new Fqdn(self::DOMAIN));
        $zone->setRecords([
            new DefaultRecord('A', self::DOMAIN, '212.204.220.100', 600),
            new DefaultRecord('A', self::DOMAIN, '185.104.29.10', 600),
            new DefaultRecord('AAAA', self::DOMAIN, '2a05:1500:900:2::100', 600),
        ]);

        $this->mockDnsZoneService
            ->method('getParkingAddressRecords')
            ->willReturn([
                new DefaultRecord('A', self::DOMAIN, '212.204.220.100', 600),
                new DefaultRecord('AAAA', self::DOMAIN, '2a05:1500:900:2::100', 600),
            ]);

        $this->mockPowerDnsClient->method('getZone')->willReturn($zone);

        $conflictingRecords = $this->dnsService->getConflictingParkingRecords(self::DOMAIN);

        self::assertCount(1, $conflictingRecords);
        self::assertSame('A', $conflictingRecords[0]->getType());
        self::assertSame('212.204.220.100', $conflictingRecords[0]->getContent());
    }

    #[Test]
    public function getConflictingParkingRecordsReturnsEmptyWhenParkingRecordStandsAlone(): void
    {
        $zone = new DnsZone(new Fqdn(self::DOMAIN));
        $zone->setRecords([
            new DefaultRecord('A', self::DOMAIN, '212.204.220.100', 600),
            new DefaultRecord('AAAA', self::DOMAIN, '2a05:1500:900:2::100', 600),
        ]);

        $this->mockDnsZoneService
            ->method('getParkingAddressRecords')
            ->willReturn([
                new DefaultRecord('A', self::DOMAIN, '212.204.220.100', 600),
                new DefaultRecord('AAAA', self::DOMAIN, '2a05:1500:900:2::100', 600),
            ]);

        $this->mockPowerDnsClient->method('getZone')->willReturn($zone);

        self::assertSame([], $this->dnsService->getConflictingParkingRecords(self::DOMAIN));
    }

    #[Test]
    public function getConflictingParkingRecordsReturnsEmptyWhenZoneNotFound(): void
    {
        $this->mockDnsZoneService
            ->method('getParkingAddressRecords')
            ->willReturn([
                new DefaultRecord('A', self::DOMAIN, '212.204.220.100', 600),
            ]);

        $this->mockPowerDnsClient->method('getZone')->willThrowException(new DnsZoneNotFoundException());

        self::assertSame([], $this->dnsService->getConflictingParkingRecords(self::DOMAIN));
    }

    #[Test]
    public function getConflictingParkingRecordsReturnsEmptyForSlaveZone(): void
    {
        $zone = new DnsZone(new Fqdn(self::DOMAIN));
        $zone->kind = PowerDnsZoneKind::SLAVE->value;
        $zone->setRecords([
            new DefaultRecord('A', self::DOMAIN, '212.204.220.100', 600),
            new DefaultRecord('A', self::DOMAIN, '185.104.29.10', 600),
        ]);

        $this->mockPowerDnsClient->method('getZone')->willReturn($zone);

        self::assertSame([], $this->dnsService->getConflictingParkingRecords(self::DOMAIN));
    }

    #[Test]
    public function removeConflictingParkingRecordsDropsParkingRecordsAndKeepsHostingRecords(): void
    {
        $zone = new DnsZone(new Fqdn(self::DOMAIN));
        $zone->setRecords([
            new DefaultRecord('A', self::DOMAIN, '212.204.220.100', 600),
            new DefaultRecord('A', self::DOMAIN, '185.104.29.10', 600),
            new DefaultRecord('AAAA', self::DOMAIN, '2a05:1500:900:2::100', 600),
            new DefaultRecord('AAAA', self::DOMAIN, '2a05:1500:900:3::20', 600),
        ]);

        $this->mockDnsZoneService
            ->method('getParkingAddressRecords')
            ->willReturn([
                new DefaultRecord('A', self::DOMAIN, '212.204.220.100', 600),
                new DefaultRecord('AAAA', self::DOMAIN, '2a05:1500:900:2::100', 600),
            ]);

        $this->mockPowerDnsClient->method('getZone')->willReturn($zone);

        $capturedZone = null;
        $this->mockPowerDnsClient
            ->expects(self::once())
            ->method('changeZone')
            ->willReturnCallback(function (DnsZone $dnsZone) use (&$capturedZone): DnsZone {
                $capturedZone = $dnsZone;

                return $dnsZone;
            });

        $removedRecords = $this->dnsService->removeConflictingParkingRecords(self::DOMAIN);

        self::assertCount(2, $removedRecords);

        self::assertInstanceOf(DnsZone::class, $capturedZone);
        $aRecords = $capturedZone->getRecordsOfTypeAndName('A', self::DOMAIN);
        self::assertCount(1, $aRecords);
        self::assertSame('185.104.29.10', $aRecords[0]->getContent());

        $aaaaRecords = $capturedZone->getRecordsOfTypeAndName('AAAA', self::DOMAIN);
        self::assertCount(1, $aaaaRecords);
        self::assertSame('2a05:1500:900:3::20', $aaaaRecords[0]->getContent());

        self::assertSame(2, DnsRecordChange::where('change_type', DnsChangeType::DELETED)->count());
    }

    #[Test]
    public function removeConflictingParkingRecordsDoesNothingWhenNoConflictExists(): void
    {
        $zone = new DnsZone(new Fqdn(self::DOMAIN));
        $zone->setRecords([
            new DefaultRecord('A', self::DOMAIN, '212.204.220.100', 600),
            new DefaultRecord('AAAA', self::DOMAIN, '2a05:1500:900:2::100', 600),
        ]);

        $this->mockDnsZoneService
            ->method('getParkingAddressRecords')
            ->willReturn([
                new DefaultRecord('A', self::DOMAIN, '212.204.220.100', 600),
                new DefaultRecord('AAAA', self::DOMAIN, '2a05:1500:900:2::100', 600),
            ]);

        $this->mockPowerDnsClient->method('getZone')->willReturn($zone);
        $this->mockPowerDnsClient->expects(self::never())->method('changeZone');

        self::assertSame([], $this->dnsService->removeConflictingParkingRecords(self::DOMAIN));
    }

    #[Test]
    public function applyDiffKeepsExistingRecordsWhenNotReplacingParkingRecords(): void
    {
        $zone = new DnsZone(new Fqdn(self::DOMAIN));
        $zone->setRecords([
            new DefaultRecord('A', self::DOMAIN, '203.0.113.1', 600),
            new DefaultRecord('NS', self::DOMAIN, 'ns1.test.nl.', 3600),
        ]);

        $this->mockDnsZoneService->expects(self::never())->method('getParkingAddressRecords');

        $dnsZoneDiff = new DnsZoneDiff([
            new AddedDnsRecord(new DefaultRecord('A', self::DOMAIN, '198.51.100.10', 600)),
        ]);

        $this->mockPowerDnsClient->expects(self::once())->method('getZone')->willReturn($zone);

        $capturedZone = null;
        $this->mockPowerDnsClient
            ->expects(self::once())
            ->method('changeZone')
            ->willReturnCallback(function (DnsZone $dnsZone) use (&$capturedZone): DnsZone {
                $capturedZone = $dnsZone;

                return $dnsZone;
            });

        $this->dnsService->applyDiff(self::DOMAIN, $dnsZoneDiff);

        self::assertInstanceOf(DnsZone::class, $capturedZone);

        self::assertCount(2, $capturedZone->getRecordsOfTypeAndName('A', self::DOMAIN));
    }

    #[Test]
    public function getOrCreateDnsSecKeysRetrievesIfExists(): void
    {
        $existingZone = new DnsZone(new Fqdn(self::DOMAIN));
        $existingZone->dnsSec = true;
        $expectedKeySet = new PowerDnsSecKeySet();

        $this->mockPowerDnsClient
            ->expects(self::once())
            ->method('getZone')
            ->with(self::DOMAIN)
            ->willReturn($existingZone);

        $this->mockPowerDnsClient
            ->expects(self::once())
            ->method('getKeys')
            ->with(self::DOMAIN)
            ->willReturn($expectedKeySet);

        $keyset = $this->dnsService->getOrCreateDnsSecKeys(self::DOMAIN);
        self::assertSame($expectedKeySet, $keyset);
    }

    #[Test]
    public function getOrCreateDnsSecKeysCreateIfMissing(): void
    {
        $existingZone = new DnsZone(new Fqdn(self::DOMAIN));
        $existingZone->dnsSec = false;
        $expectedKeySet = new PowerDnsSecKeySet();

        $this->mockPowerDnsClient
            ->expects(self::exactly(2))
            ->method('getZone')
            ->with(self::DOMAIN)
            ->willReturn($existingZone);

        $this->mockPowerDnsClient
            ->expects(self::once())
            ->method('updateZone')
            ->with(self::callback(fn (DnsZone $zone) => $zone->dnsSec))
            ->willReturn($existingZone);

        $this->mockPowerDnsClient
            ->expects(self::once())
            ->method('getKeys')
            ->with(self::DOMAIN)
            ->willReturn($expectedKeySet);

        $keyset = $this->dnsService->getOrCreateDnsSecKeys(self::DOMAIN);
        self::assertSame($expectedKeySet, $keyset);
    }
}
