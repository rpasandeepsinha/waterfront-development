<?php

declare(strict_types=1);

namespace Tests\Domain\DNS\Actions;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;
use RealtimeRegister\RealtimeRegister;
use stdClass;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DnsDeploymentFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\DomainProviderBusinessUnitFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Infra\PowerDnsClient\PowerDnsMockHelper;
use Tests\Infra\RtrClient\Helpers\MockedClientFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\DNS\Actions\AssignNameserversToDomainAction;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\DTO\Nameserver;
use Waterfront\Domain\DNS\Factories\NameserverAssignerFactory;
use Waterfront\Domain\DNS\Repository\DnsDeploymentRepository;
use Waterfront\Domain\DNS\Repository\DnsProductSpecRepository;
use Waterfront\Domain\DNS\Services\DnsNameserverAssigner;
use Waterfront\Domain\DNS\Services\PowerDnsNameserverSynchronizer;
use Waterfront\Domain\Domains\Factories\DomainServiceFactory;
use Waterfront\Domain\Domains\Interfaces\DomainDriverInterface;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;

#[CoversClass(AssignNameserversToDomainAction::class)]
#[AllowMockObjectsWithoutExpectations]
class AssignNameserversToDomainActionTest extends IntegrationTestCase
{
    use PowerDnsMockHelper;

    private DnsNameserverAssigner&MockObject $mockNameserverAssigner;

    private NameserverAssignerFactory&MockObject $mockNameserverAssignerFactory;

    private DomainServiceFactory&MockObject $mockDomainServiceFactory;

    private PowerDnsNameserverSynchronizer&MockObject $mockPowerDnsNameserverSynchronizer;

    private DnsService&MockObject $mockDnsService;

    private DomainDriverInterface&MockObject $mockDomainDriver;

    private DnsProductSpecRepository&MockObject $mockDnsProductSpecRepository;

    private DnsDeploymentRepository&MockObject $mockDnsDeploymentRepository;

    private LoggerInterface&Stub $mockLoggerInterface;

    public function setUp(): void
    {
        parent::setUp();

        $this->mockNameserverAssigner = self::createMock(DnsNameserverAssigner::class);
        $this->mockNameserverAssignerFactory = self::createMock(NameserverAssignerFactory::class);
        $this->mockDomainServiceFactory = self::createMock(DomainServiceFactory::class);
        $this->mockPowerDnsNameserverSynchronizer = self::createMock(PowerDnsNameserverSynchronizer::class);
        $this->mockDnsService = self::createMock(DnsService::class);
        $this->mockDomainDriver = self::createMock(DomainDriverInterface::class);
        $this->mockDnsProductSpecRepository = self::createMock(DnsProductSpecRepository::class);
        $this->mockDnsDeploymentRepository = self::createMock(DnsDeploymentRepository::class);
        $this->mockLoggerInterface = self::createStub(LoggerInterface::class);
    }

    #[Test]
    public function assignNameserversToPremiumDnsWithDomainBusinessUnit(): void
    {
        $testNameservers = [
            new Nameserver('ns1.test.nl'),
            new Nameserver('ns2.test.nl'),
            new Nameserver('ns3.test.nl'),
            new Nameserver('ns3.test.nl'),
        ];

        $testDomain = 'sandwave.io';

        $domainDeployment = new DomainDeploymentFactory()
            ->withSubscription(
                new ProductFactory()->for(new ProductGroupFactory()->extension())->createOne(),
                [
                    'domain' => $testDomain,
                ],
            )
            ->for(DomainProviderBusinessUnitFactory::new()->argeweb(), 'businessUnit')
            ->withRtrProvider()
            ->createOne();

        $dnsSubscription = new SubscriptionFactory()
            ->administrativeStatusActive()
            ->technicalStatus(TechnicalStatus::FAILED->value)
            ->forDomain($testDomain)
            ->for(new CustomerFactory())
            ->for(new ProductFactory()->premiumDns()->has(
                new ProductSpecFactory()->state([
                    'name' => ProductSpecName::DNS_IS_PREMIUM->value,
                    'value' => true,
                ]),
            ))
            ->state([
                'parent_subscription_id' => $domainDeployment->subscription->id,
            ])
            ->createOne();

        $dnsDeployment = DnsDeploymentFactory::new()->withVanityNameserver()->for($dnsSubscription)->createOne();

        $this->mockDnsDeploymentRepository
            ->expects(self::once())
            ->method('getDnsDeploymentFromDomain')
            ->willReturn($dnsDeployment);

        $this->mockDnsDeploymentRepository
            ->expects(self::once())
            ->method('isNameserversAlreadyAssigned')
            ->willReturn(false);

        $this->mockNameserverAssignerFactory
            ->expects(self::once())
            ->method('createAssigner')
            ->with($dnsDeployment->nameserver_type)
            ->willReturn($this->mockNameserverAssigner);

        $this->mockNameserverAssigner
            ->expects(self::once())
            ->method('assign')
            ->with($dnsDeployment)
            ->willReturn($testNameservers);

        $this->mockDnsProductSpecRepository->expects(self::once())->method('isPremiumDns')->willReturn(true);

        $this->mockPowerDnsNameserverSynchronizer->expects(self::never())->method('synchronize');

        $this->mockDnsService->expects(self::once())->method('enablePremiumDns')->with($testDomain);

        $this->mockDomainServiceFactory
            ->expects(self::once())
            ->method('driver')
            ->with(
                $domainDeployment->provider->slug,
                $domainDeployment->businessUnit,
            )
            ->willReturn($this->mockDomainDriver);

        $this->mockDomainDriver
            ->expects(self::once())
            ->method('updateNameServers')
            ->with(
                $testDomain,
                self::callback(fn (array $nameservers) => $this->checkUniqueNameserver($nameservers)),
            );

        $assignAction = new AssignNameserversToDomainAction(
            domainServiceFactory: $this->mockDomainServiceFactory,
            powerDnsNameserverSynchronizer: $this->mockPowerDnsNameserverSynchronizer,
            dnsService: $this->mockDnsService,
            dnsProductSpecRepository: $this->mockDnsProductSpecRepository,
            dnsDeploymentRepository: $this->mockDnsDeploymentRepository,
            nameserverAssignerFactory: $this->mockNameserverAssignerFactory,
            logger: $this->mockLoggerInterface,
        );

        $assignAction->assign($domainDeployment);

        self::assertSame(TechnicalStatus::PENDING->value, $dnsSubscription->refresh()->technical_status);
    }

    #[Test]
    public function assignNameserversToPremiumDns(): void
    {
        $testNameservers = [
            new Nameserver('ns1.test.nl'),
            new Nameserver('ns2.test.nl'),
            new Nameserver('ns3.test.nl'),
        ];

        $testDomain = 'sandwave.io';

        $domainDeployment = new DomainDeploymentFactory()
            ->withSubscription(
                new ProductFactory()->for(new ProductGroupFactory()->extension())->createOne(),
                [
                    'domain' => $testDomain,
                ],
            )
            ->withRtrProvider()
            ->createOne();

        $dnsSubscription = new SubscriptionFactory()
            ->administrativeStatusActive()
            ->technicalStatus(TechnicalStatus::FAILED->value)
            ->forDomain($testDomain)
            ->for(new CustomerFactory())
            ->for(new ProductFactory()->premiumDns()->has(
                new ProductSpecFactory()->state([
                    'name' => ProductSpecName::DNS_IS_PREMIUM->value,
                    'value' => true,
                ]),
            ))
            ->state([
                'parent_subscription_id' => $domainDeployment->subscription->id,
            ])
            ->createOne();

        $dnsDeployment = DnsDeploymentFactory::new()->withVanityNameserver()->for($dnsSubscription)->createOne();

        $this->mockDnsDeploymentRepository
            ->expects(self::once())
            ->method('getDnsDeploymentFromDomain')
            ->willReturn($dnsDeployment);

        $this->mockDnsDeploymentRepository
            ->expects(self::once())
            ->method('isNameserversAlreadyAssigned')
            ->willReturn(false);

        $this->mockNameserverAssignerFactory
            ->expects(self::once())
            ->method('createAssigner')
            ->with($dnsDeployment->nameserver_type)
            ->willReturn($this->mockNameserverAssigner);

        $this->mockNameserverAssigner
            ->expects(self::once())
            ->method('assign')
            ->with($dnsDeployment)
            ->willReturn($testNameservers);

        $this->mockDnsProductSpecRepository->expects(self::once())->method('isPremiumDns')->willReturn(true);

        $this->mockPowerDnsNameserverSynchronizer->expects(self::never())->method('synchronize');

        $this->mockDnsService->expects(self::once())->method('enablePremiumDns')->with($testDomain);

        $this->mockDomainServiceFactory
            ->expects(self::once())
            ->method('driver')
            ->with(
                $domainDeployment->provider->slug,
                null, // No business unit in this test
            )
            ->willReturn($this->mockDomainDriver);

        $this->mockDomainDriver
            ->expects(self::once())
            ->method('updateNameServers')
            ->with($testDomain, $testNameservers);

        $assignAction = new AssignNameserversToDomainAction(
            domainServiceFactory: $this->mockDomainServiceFactory,
            powerDnsNameserverSynchronizer: $this->mockPowerDnsNameserverSynchronizer,
            dnsService: $this->mockDnsService,
            dnsProductSpecRepository: $this->mockDnsProductSpecRepository,
            dnsDeploymentRepository: $this->mockDnsDeploymentRepository,
            nameserverAssignerFactory: $this->mockNameserverAssignerFactory,
            logger: $this->mockLoggerInterface,
        );

        $assignAction->assign($domainDeployment);

        self::assertSame(TechnicalStatus::PENDING->value, $dnsSubscription->refresh()->technical_status);
    }

    #[Test]
    public function assignNameserversToFreeDns(): void
    {
        $testNameservers = [
            new Nameserver('ns1.test.nl'),
            new Nameserver('ns2.test.nl'),
            new Nameserver('ns3.test.nl'),
            new Nameserver('NS1.test.nl'),
            new Nameserver('ns2.test.nl'),
            new Nameserver('ns3.test.nl'),
        ];

        $testDomain = 'sandwave.io';

        $domainDeployment = new DomainDeploymentFactory()
            ->withSubscription(
                new ProductFactory()->for(new ProductGroupFactory()->extension())->createOne(),
                [
                    'domain' => $testDomain,
                ],
            )
            ->withRtrProvider()
            ->createOne();

        $dnsSubscription = new SubscriptionFactory()
            ->administrativeStatusActive()
            ->forDomain($testDomain)
            ->for(new CustomerFactory())
            ->for(
                new ProductFactory()->for(new ProductGroupFactory()->dns())->freeDns(),
            )
            ->createOne();

        $dnsDeployment = DnsDeploymentFactory::new()->for($dnsSubscription)->withInternalNameserver()->createOne();

        $this->mockDnsDeploymentRepository
            ->expects(self::once())
            ->method('getDnsDeploymentFromDomain')
            ->willReturn($dnsDeployment);

        $this->mockDnsDeploymentRepository
            ->expects(self::once())
            ->method('isNameserversAlreadyAssigned')
            ->willReturn(false);

        $this->mockNameserverAssignerFactory
            ->expects(self::once())
            ->method('createAssigner')
            ->with($dnsDeployment->nameserver_type)
            ->willReturn($this->mockNameserverAssigner);

        $this->mockNameserverAssigner
            ->expects(self::once())
            ->method('assign')
            ->with($dnsDeployment)
            ->willReturn($testNameservers);

        $this->mockDnsProductSpecRepository->expects(self::once())->method('isPremiumDns')->willReturn(false);

        $this->mockPowerDnsNameserverSynchronizer
            ->expects(self::once())
            ->method('synchronize')
            ->with(
                $testDomain,
                self::callback(fn (array $nameservers) => $this->checkUniqueNameserver($nameservers)),
                false,
            );

        $this->mockDnsService->expects(self::never())->method('enablePremiumDns')->with($testDomain);

        $this->mockDomainServiceFactory
            ->expects(self::once())
            ->method('driver')
            ->with(
                $domainDeployment->provider->slug,
                null, // No business unit for free DNS
            )
            ->willReturn($this->mockDomainDriver);

        $this->mockDomainDriver
            ->expects(self::once())
            ->method('updateNameServers')
            ->with(
                $testDomain,
                self::callback(fn (array $nameservers) => $this->checkUniqueNameserver($nameservers)),
            );

        $assignAction = new AssignNameserversToDomainAction(
            domainServiceFactory: $this->mockDomainServiceFactory,
            powerDnsNameserverSynchronizer: $this->mockPowerDnsNameserverSynchronizer,
            dnsService: $this->mockDnsService,
            dnsProductSpecRepository: $this->mockDnsProductSpecRepository,
            dnsDeploymentRepository: $this->mockDnsDeploymentRepository,
            nameserverAssignerFactory: $this->mockNameserverAssignerFactory,
            logger: $this->mockLoggerInterface,
        );

        $assignAction->assign($domainDeployment);
    }

    #[Test]
    public function assignsNameserversToSubscriptionFromDomain(): void
    {
        $domainDeployment = new DomainDeploymentFactory()
            ->withSubscription(
                new ProductFactory()->for(new ProductGroupFactory()->extension())->createOne(),
                ['domain' => 'sandwave.io'],
            )
            ->withRtrProvider()
            ->createOne();

        $dnsSubscription = new SubscriptionFactory()
            ->administrativeStatusActive()
            ->forDomain('sandwave.io')
            ->for(new CustomerFactory())
            ->for(
                new ProductFactory()->for(new ProductGroupFactory()->dns())->freeDns(),
            )
            ->parentSubscription($domainDeployment->subscription)
            ->createOne();

        $dnsDeployment = DnsDeploymentFactory::new()->for($dnsSubscription)->createOne();

        $rtrSdk = MockedClientFactory::makeSdk(
            200,
            '',
            static function (RequestInterface $request) use ($dnsDeployment) {
                self::assertSame('v2/domains/sandwave.io/update', $request->getUri()->getPath());

                $nameservers = $dnsDeployment->dnsNameservers->pluck('nameserver');

                /** @var stdClass $data */
                $data = json_decode($request->getBody()->getContents(), null, 512, JSON_THROW_ON_ERROR);
                /** @var array<string> $nsRecords */
                $nsRecords = $data->ns;

                self::assertCount(3, $nsRecords);
                self::assertSame($nameservers[0], $nsRecords[0]);
                self::assertSame($nameservers[1], $nsRecords[1]);
            },
        );

        $pdnsMock = $this->makePdnsWithMultipleResponses(
            [
                // get DNS zone
                new Response(
                    200,
                    [],
                    $this->getMockedZoneResponseBody('sandwave.io'),
                ),
                new Response(
                    204,
                ),
            ],
            static function (RequestInterface $request) use ($dnsDeployment): void {
                if ($request->getMethod() !== 'PATCH') {
                    return;
                }

                $dnsDeployment->refresh();
                /** @var array<int,string> $nameservers */
                $nameservers = $dnsDeployment->dnsNameservers->pluck('nameserver')->toArray();

                /** @var stdClass $data */
                $data = json_decode($request->getBody()->getContents(), null, 512, JSON_THROW_ON_ERROR);

                /** @var array<stdClass> $rrSets */
                $rrSets = $data->rrsets;

                /** @var stdClass $recordSet */
                $recordSet = $rrSets[0];

                self::assertCount(2, $rrSets);

                if ($recordSet->type === 'SOA') {
                    self::assertSame('sandwave.io.', $recordSet->name);
                    self::assertSame(3600, $recordSet->ttl);
                    self::assertSame('SOA', $recordSet->type);
                    self::assertSame('REPLACE', $recordSet->changetype);
                    /** @var array<stdClass> $records */
                    $records = $recordSet->records;
                    self::assertCount(1, $records);
                    $soaRecord = $records[0];

                    self::assertSame(
                        sprintf('%s. hostmaster.sandwave.io. 2022050502 10800 3600 604800 3600', $nameservers[0]),
                        $soaRecord->content,
                    );
                    self::assertFalse($soaRecord->disabled);
                }

                if ($recordSet->type === 'NS') {
                    self::assertSame('sandwave.io.', $recordSet->name);
                    self::assertSame(3600, $recordSet->ttl);
                    self::assertSame('NS', $recordSet->type);
                    self::assertSame('REPLACE', $recordSet->changetype);
                    /** @var array<stdClass> $records */
                    $records = $recordSet->records;
                    self::assertCount(3, $records);
                    self::assertSame(sprintf('%s.', $nameservers[0]), $records[0]->content);
                    self::assertFalse($records[0]->disabled);
                    self::assertSame(sprintf('%s.', $nameservers[1]), $records[1]->content);
                    self::assertFalse($records[1]->disabled);
                    self::assertSame(sprintf('%s.', $nameservers[2]), $records[2]->content);
                    self::assertFalse($records[2]->disabled);
                }
            },
        );

        $this->pdns($pdnsMock);
        $this->app->bind(RealtimeRegister::class, static fn () => $rtrSdk);

        self::assertEmpty($dnsDeployment->dnsNameservers);
        self::resolve(AssignNameserversToDomainAction::class)->assignToDomain('sandwave.io');
        self::assertNotEmpty($dnsDeployment->dnsNameservers);
    }

    #[Test]
    public function assignsNameserversWithoutProvision(): void
    {
        $testDomain = 'sandwave.io';
        $testNameservers = [
            new Nameserver('ns1.test.nl'),
            new Nameserver('ns2.test.nl'),
            new Nameserver('ns3.test.nl'),
        ];

        $domainDeployment = new DomainDeploymentFactory()
            ->withSubscription(
                new ProductFactory()->for(new ProductGroupFactory()->extension())->createOne(),
                ['domain' => $testDomain],
            )
            ->withRtrProvider()
            ->createOne();

        $dnsSubscription = new SubscriptionFactory()
            ->administrativeStatusActive()
            ->forDomain($testDomain)
            ->for(new CustomerFactory())
            ->for(
                new ProductFactory()->for(new ProductGroupFactory()->dns())->freeDns(),
            )
            ->createOne();

        $dnsDeployment = DnsDeploymentFactory::new()->for($dnsSubscription)->withInternalNameserver()->createOne();

        $this->mockNameserverAssignerFactory
            ->expects(self::once())
            ->method('createAssigner')
            ->with($dnsDeployment->nameserver_type)
            ->willReturn($this->mockNameserverAssigner);

        $this->mockDnsDeploymentRepository
            ->expects(self::once())
            ->method('getDnsDeploymentFromDomain')
            ->willReturn($dnsDeployment);

        $this->mockDnsDeploymentRepository
            ->expects(self::once())
            ->method('isNameserversAlreadyAssigned')
            ->willReturn(false);

        $this->mockNameserverAssigner
            ->expects(self::once())
            ->method('assign')
            ->with($dnsDeployment)
            ->willReturn($testNameservers);

        $this->mockDnsProductSpecRepository->expects(self::never())->method('isPremiumDns');

        $this->mockDnsService->expects(self::never())->method('enablePremiumDns');

        $this->mockPowerDnsNameserverSynchronizer->expects(self::never())->method('synchronize');

        $this->mockDomainServiceFactory->expects(self::never())->method('driver');

        $action = new AssignNameserversToDomainAction(
            domainServiceFactory: $this->mockDomainServiceFactory,
            powerDnsNameserverSynchronizer: $this->mockPowerDnsNameserverSynchronizer,
            dnsService: $this->mockDnsService,
            dnsProductSpecRepository: $this->mockDnsProductSpecRepository,
            dnsDeploymentRepository: $this->mockDnsDeploymentRepository,
            nameserverAssignerFactory: $this->mockNameserverAssignerFactory,
            logger: $this->mockLoggerInterface,
        );

        $action->assign($domainDeployment, false);
    }

    /**
     * @param Nameserver[] $nameservers
     */
    private function checkUniqueNameserver(array $nameservers): bool
    {
        self::assertContainsOnlyInstancesOf(Nameserver::class, $nameservers);

        $hostnames = array_map(fn (Nameserver $ns) => $ns->hostname, $nameservers);

        $uniqueHostnames = array_unique($hostnames);

        self::assertCount(3, $uniqueHostnames, 'Expected exactly 3 unique nameservers');
        self::assertSame(
            $uniqueHostnames,
            $hostnames,
            'Nameservers should not contain duplicates',
        );

        return true;
    }
}
