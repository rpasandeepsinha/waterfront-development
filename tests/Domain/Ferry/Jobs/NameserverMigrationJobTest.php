<?php

declare(strict_types=1);

namespace Tests\Domain\Ferry\Jobs;

use GuzzleHttp\Psr7\Response;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;
use RealtimeRegister\RealtimeRegister;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DnsDeploymentFactory;
use Tests\Factories\DnsNameserverFactory;
use Tests\Factories\DnsRegionFactory;
use Tests\Factories\DomainContactFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\MigratedCustomersFactory;
use Tests\Factories\MigratedSubscriptionsFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Infra\PowerDnsClient\PowerDnsMockHelper;
use Tests\Infra\RtrClient\Helpers\MockedClientFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\DNS\Actions\AssignNameserversToDomainAction;
use Waterfront\Domain\Domains\DomainService;
use Waterfront\Domain\Domains\DTO\DomainDetailsDTO;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Domains\Exceptions\DomainModificationFailedException;
use Waterfront\Domain\Ferry\Jobs\NameserverMigrationJob;
use Waterfront\Domain\Ferry\Services\AdfPayloadService;
use Waterfront\Domain\Provision\DNS\Enums\NameserverType;
use Waterfront\Infra\PowerDnsClient\Exceptions\PdnsResponseException;

#[CoversClass(NameserverMigrationJob::class)]
class NameserverMigrationJobTest extends IntegrationTestCase
{
    use PowerDnsMockHelper;

    private const string TEST_DOMAIN = 'test-domain.testing';

    #[Test]
    public function rollbackClean(): void
    {
        Http::fake();

        $customer = CustomerFactory::new()->createOne();

        $extensionProductGroup = ProductGroupFactory::new()->extension()->createOne();

        $extensionProduct = ProductFactory::new()->for($extensionProductGroup)->createOne();

        $extensionSubscription = SubscriptionFactory::new()->for($customer)->for($extensionProduct)->createOne([
            'domain' => self::TEST_DOMAIN,
            'technical_status' => DomainStatus::ACTIVE->value,
        ]);

        $migratedSubscription = MigratedSubscriptionsFactory::new()->createOne();
        $extensionSubscription->migratedSubscriptions()->attach($migratedSubscription);
        $extensionSubscription->save();

        $product = ProductFactory::new()->for(ProductGroupFactory::new()->dns())->freeDns()->createOne();
        $dnsSubscription = SubscriptionFactory::new()
            ->administrativeStatusActive()
            ->forDomain(self::TEST_DOMAIN)
            ->for($customer)
            ->for($product)
            ->parentSubscription($extensionSubscription)
            ->createOne();

        $dnsDeployment = DnsDeploymentFactory::new()->for($dnsSubscription)->createOne();

        $migrationCustomer = MigratedCustomersFactory::new()->createOne();
        $migrationCustomer->migratedSubscriptions()->attach($migratedSubscription);
        $migrationCustomer->customers()->attach($customer);

        $domainContact = DomainContactFactory::new()->for($extensionSubscription->customer)->createOne();

        $deployment = DomainDeploymentFactory::new()->withRtrProvider()->createOne([
            'subscription_uuid' => $extensionSubscription->uuid,
            'contact_owner_id' => $domainContact->id,
        ]);

        $dnsRegion = DnsRegionFactory::new()->createOne();

        DnsNameserverFactory::new()->for($dnsRegion)->count(3)->createOne();

        $domainDetailsResponse = include __DIR__ . '/data/domain_details_valid.php';
        $domainDetailsResponse['domain'] = self::TEST_DOMAIN;
        $domainDetailsResponse['ns'] = [
            'ns1.testing.test',
            'nameserver1337.testing.test',
        ];

        $rtrRequests = [];

        $rtrSdk = MockedClientFactory::makeSdkWithMultipleReponses(
            [
                new Response(200, [], json_encode($domainDetailsResponse, JSON_THROW_ON_ERROR)), // hasModernInternalNameservers
                new Response(200, [], json_encode($domainDetailsResponse, JSON_THROW_ON_ERROR)), // hasLegacyInternalNameservers
                new Response(200, []),
            ],
            static function (RequestInterface $request) use (&$rtrRequests): void {
                $rtrRequests[] = $request;
            },
        );

        $this->app->instance(RealtimeRegister::class, $rtrSdk);

        $pdnsRequests = [];

        $pdnsMock = $this->makePdnsWithMultipleResponses(
            [
                // get DNS zone
                new Response(
                    200,
                    [],
                    $this->getMockedZoneResponseBody(self::TEST_DOMAIN),
                ),
                // Force an error to trigger the handle.
                new Response(
                    500,
                    [],
                    $this->getMockedZoneResponseBody(self::TEST_DOMAIN),
                ),
            ],
            static function (RequestInterface $request) use (&$pdnsRequests): void {
                $pdnsRequests[] = $request;
            },
        );

        $this->pdns($pdnsMock);

        $this->app->forgetInstance('domain-service-realtime_register');

        $job = new NameserverMigrationJob($extensionSubscription, $extensionSubscription->technical_status);
        $dispatcher = self::resolve(Dispatcher::class);

        self::expectException(PdnsResponseException::class);

        $dispatcher->dispatch($job);

        self::assertCount(3, $dnsDeployment->dnsNameservers);
    }

    #[Test]
    public function rollbackWithPreExistingNameservers(): void
    {
        Http::fake();

        $customer = CustomerFactory::new()->createOne();

        $extensionProductGroup = ProductGroupFactory::new()->extension()->createOne();

        $extensionProduct = ProductFactory::new()->for($extensionProductGroup)->createOne();

        $extensionSubscription = SubscriptionFactory::new()->for($customer)->for($extensionProduct)->createOne([
            'domain' => self::TEST_DOMAIN,
            'technical_status' => DomainStatus::ACTIVE->value,
        ]);

        $product = ProductFactory::new()->for(ProductGroupFactory::new()->dns())->freeDns()->createOne();
        $dnsSubscription = SubscriptionFactory::new()
            ->administrativeStatusActive()
            ->forDomain(self::TEST_DOMAIN)
            ->for($customer)
            ->for($product)
            ->parentSubscription($extensionSubscription)
            ->createOne();

        $dnsDeployment = DnsDeploymentFactory::new()->for($dnsSubscription)->createOne();

        $migratedSubscription = MigratedSubscriptionsFactory::new()->createOne();
        $extensionSubscription->migratedSubscriptions()->attach($migratedSubscription);
        $extensionSubscription->save();

        $migrationCustomer = MigratedCustomersFactory::new()->createOne();
        $migrationCustomer->migratedSubscriptions()->attach($migratedSubscription);
        $migrationCustomer->customers()->attach($customer);

        $domainContact = DomainContactFactory::new()->for($extensionSubscription->customer)->createOne();

        $deployment = DomainDeploymentFactory::new()->withRtrProvider()->createOne([
            'subscription_uuid' => $extensionSubscription->uuid,
            'contact_owner_id' => $domainContact->id,
        ]);

        $dnsRegion = DnsRegionFactory::new()->createOne();

        DnsNameserverFactory::new()->for($dnsRegion)->count(3)->createOne();
        $preExisingNameServer = DnsNameserverFactory::new()->for($dnsRegion)->createOne();

        $dnsDeployment->dnsNameservers()->attach($preExisingNameServer);

        $domainDetailsResponse = include __DIR__ . '/data/domain_details_valid.php';
        $domainDetailsResponse['domain'] = self::TEST_DOMAIN;
        $domainDetailsResponse['ns'] = [
            'ns1.testing.test',
            'nameserver1337.testing.test',
        ];

        $rtrRequests = [];

        $rtrSdk = MockedClientFactory::makeSdkWithMultipleReponses(
            [
                new Response(200, [], json_encode($domainDetailsResponse, JSON_THROW_ON_ERROR)), // hasModernInternalNameservers
                new Response(200, [], json_encode($domainDetailsResponse, JSON_THROW_ON_ERROR)), // hasLegacyInternalNameservers
                new Response(200, []),
            ],
            static function (RequestInterface $request) use (&$rtrRequests): void {
                $rtrRequests[] = $request;
            },
        );

        $this->app->instance(RealtimeRegister::class, $rtrSdk);

        $pdnsRequests = [];

        $pdnsMock = $this->makePdnsWithMultipleResponses(
            [
                // get DNS zone
                new Response(
                    200,
                    [],
                    $this->getMockedZoneResponseBody(self::TEST_DOMAIN),
                ),
                // Force an error to trigger the handle.
                new Response(
                    500,
                    [],
                    $this->getMockedZoneResponseBody(self::TEST_DOMAIN),
                ),
            ],
            static function (RequestInterface $request) use (&$pdnsRequests): void {
                $pdnsRequests[] = $request;
            },
        );

        $this->pdns($pdnsMock);

        $this->app->forgetInstance('domain-service-realtime_register');

        $job = new NameserverMigrationJob($extensionSubscription, $extensionSubscription->technical_status);
        $dispatcher = self::resolve(Dispatcher::class);

        try {
            $dispatcher->dispatch($job);
            self::fail('This point should not be reached');
        } catch (PdnsResponseException) {
            self::assertCount(1, $dnsDeployment->dnsNameservers);
        }
    }

    #[DataProvider('nameserverMigrationJobProvider')]
    #[Test]
    public function nameserverJob(
        string $domain,
        bool $shouldResetDnssec,
    ): void {
        Http::fake();

        $customer = CustomerFactory::new()->createOne();

        $extensionProductGroup = ProductGroupFactory::new()->extension()->createOne();

        $extensionProduct = ProductFactory::new()->for($extensionProductGroup)->createOne();

        $extensionSubscription = SubscriptionFactory::new()
            ->for($customer)
            ->for($extensionProduct)
            ->technicalStatusDomainActive()
            ->forDomain($domain)
            ->createOne();

        $dnsSubscriptionCom = SubscriptionFactory::new()
            ->administrativeStatusActive()
            ->forDomain(self::TEST_DOMAIN)
            ->for($customer)
            ->for($extensionProduct)
            ->parentSubscription($extensionSubscription)
            ->createOne();

        DnsDeploymentFactory::new()->for($dnsSubscriptionCom)->createOne();

        $migratedSubscription = MigratedSubscriptionsFactory::new()->createOne();
        $extensionSubscription->migratedSubscriptions()->attach($migratedSubscription);
        $extensionSubscription->save();

        $migrationCustomer = MigratedCustomersFactory::new()->createOne();
        $migrationCustomer->migratedSubscriptions()->attach($migratedSubscription);
        $migrationCustomer->customers()->attach($customer);

        DomainDeploymentFactory::new()->for($extensionSubscription)->withRtrProvider()->createOne();

        $nameserversActionMock = self::createMock(AssignNameserversToDomainAction::class);
        $invokedCount = $shouldResetDnssec ? self::exactly(2) : self::once();
        $nameserversActionMock
            ->expects($invokedCount)
            ->method('assignToDomain')
            ->willReturnCallback(function () use ($shouldResetDnssec, $invokedCount): void {
                if ($shouldResetDnssec && $invokedCount->numberOfInvocations() === 1) {
                    // Only throw an exception on the first try, but not the second try.
                    throw new DomainModificationFailedException('Domain update is failed'); // Yes, that is an actual Openprovider message
                }

                // Else it just returns void.
            });

        $this->app->bind(AssignNameserversToDomainAction::class, fn () => $nameserversActionMock);

        $domainServiceMock = self::createMock(DomainService::class);
        $domainDetailsDTO = self::createStub(DomainDetailsDTO::class);
        $domainDetailsDTO->ns = [
            'ns1.testing.test',
            'nameserver1337.testing.test',
        ];
        $domainServiceMock->expects(self::exactly(2))->method('fetchDomain')->willReturn($domainDetailsDTO);
        $domainServiceMock->expects($shouldResetDnssec ? self::once() : self::never())->method('disableDnssec');
        $domainServiceMock->expects($shouldResetDnssec ? self::once() : self::never())->method('enableDnssec');
        $this->app->bind(DomainService::class, fn () => $domainServiceMock);

        $job = new NameserverMigrationJob($extensionSubscription, $extensionSubscription->technical_status);
        $job->handle(
            self::resolve(AdfPayloadService::class),
            self::resolve(Dispatcher::class),
            self::resolve(LoggerInterface::class),
        );
    }

    /**
     * @return iterable<string, mixed>
     */
    public static function nameserverMigrationJobProvider(): iterable
    {
        yield '.nl domain' => [
            'domain' => 'test-domain.nl',
            'shouldResetDnssec' => false,
        ];

        yield '.eu domain' => [
            'domain' => 'test-domain.eu',
            'shouldResetDnssec' => true,
        ];
    }

    #[Test]
    public function nameserverJobRetryAlreadyInternal(): void
    {
        Http::fake();

        $customer = CustomerFactory::new()->createOne();

        $extensionProductGroup = ProductGroupFactory::new()->extension()->createOne();

        $extensionProduct = ProductFactory::new()->for($extensionProductGroup)->createOne();

        $extensionSubscription = SubscriptionFactory::new()
            ->for($customer)
            ->for($extensionProduct)
            ->technicalStatusDomainActive()
            ->forDomain(self::TEST_DOMAIN)
            ->createOne();

        DomainDeploymentFactory::new()->for($extensionSubscription)->withRtrProvider()->createOne();

        $dnsSubscriptionCom = SubscriptionFactory::new()
            ->administrativeStatusActive()
            ->forDomain(self::TEST_DOMAIN)
            ->for($customer)
            ->for($extensionProduct)
            ->parentSubscription($extensionSubscription)
            ->createOne();

        $region = new DnsRegionFactory()->createOne();
        $nameserver1 = new DnsNameserverFactory()->for($region)->createOne(['nameserver' => 'ns1.testing.test']);
        $nameserver2 = new DnsNameserverFactory()->for($region)->createOne(['nameserver' => 'ns2.testing.test']);

        $dnsDeployment = DnsDeploymentFactory::new()->for($dnsSubscriptionCom)->createOne([
            'nameserver_type' => NameserverType::INTERNAL,
        ]);

        $dnsDeployment->dnsNameservers()->attach([$nameserver1, $nameserver2]);

        $migratedSubscription = MigratedSubscriptionsFactory::new()->createOne();
        $extensionSubscription->migratedSubscriptions()->attach($migratedSubscription);
        $extensionSubscription->save();

        $migrationCustomer = MigratedCustomersFactory::new()->createOne();
        $migrationCustomer->migratedSubscriptions()->attach($migratedSubscription);
        $migrationCustomer->customers()->attach($customer);

        $nameserversActionMock = self::createMock(AssignNameserversToDomainAction::class);
        $nameserversActionMock
            ->expects(self::never()) // we're not assigning because it's already internal
            ->method('assignToDomain');

        $this->app->bind(AssignNameserversToDomainAction::class, fn () => $nameserversActionMock);

        $domainDetailsDTO = self::createStub(DomainDetailsDTO::class);
        $domainDetailsDTO->ns = [
            'ns1.testing.test',
            'ns2.testing.test',
        ];
        $domainServiceMock = self::createMock(DomainService::class);
        $domainServiceMock->expects(self::once())->method('fetchDomain')->willReturn($domainDetailsDTO);
        $this->app->bind(DomainService::class, fn () => $domainServiceMock);

        $job = new NameserverMigrationJob($extensionSubscription, $extensionSubscription->technical_status);
        $job->handle(
            self::resolve(AdfPayloadService::class),
            self::resolve(Dispatcher::class),
            self::resolve(LoggerInterface::class),
        );

        $dnsDeployment->refresh();

        // Should still be type internal
        self::assertEquals(NameserverType::INTERNAL, $dnsDeployment->nameserver_type);
    }
}
