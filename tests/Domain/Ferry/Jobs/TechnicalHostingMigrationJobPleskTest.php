<?php

declare(strict_types=1);

namespace Tests\Domain\Ferry\Jobs;

use Illuminate\Bus\Dispatcher;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Tests\Factories\CustomerFactory;
use Tests\Factories\HostingDeploymentFactory;
use Tests\Factories\MigratedCustomersFactory;
use Tests\Factories\MigratedSubscriptionsFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ServerFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Models\MigratedSubscription;
use Waterfront\Domain\Ferry\Actions\Hosting\HostingCanGenerateSSOAction;
use Waterfront\Domain\Ferry\Dto\Hosting\HostingMigrationPayload;
use Waterfront\Domain\Ferry\Dto\Hosting\PleskHostingDetails;
use Waterfront\Domain\Ferry\Exceptions\HostingMigrationIsResellerException;
use Waterfront\Domain\Ferry\Exceptions\HostingUnableToSetDefaultDomainException;
use Waterfront\Domain\Ferry\Jobs\TechnicalHostingMigrationJob;
use Waterfront\Domain\Ferry\Services\AdfPayloadService;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Hosting\Plesk\Services\PleskHostingService;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\DirectAdminClient\DTO\UserConfig;
use Waterfront\Infra\DirectAdminClient\Enums\HostingUserType;
use Waterfront\Infra\PleskClient\DTO\PleskHostingPackage;
use Waterfront\Infra\PleskClient\Exceptions\PleskClientException;

#[CoversClass(TechnicalHostingMigrationJob::class)]
#[AllowMockObjectsWithoutExpectations]
class TechnicalHostingMigrationJobPleskTest extends IntegrationTestCase
{
    private const string TEST_PLESK_SERVER = 'test.plesk.test';

    private Subscription $pleskSubscription;

    private MigratedSubscription $migratedSubscription;

    private Product $extensionProduct;

    private Customer $customer;

    private string $pleskUsername;

    private int $pleskCustomerId;

    private Server $server;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();

        $this->customer = CustomerFactory::new()->createOne();

        $extensionHostingGroup = ProductGroupFactory::new()->hosting()->createOne();
        $extensionGroup = ProductGroupFactory::new()->extension()->createOne();

        $hostingProduct = ProductFactory::new()->for($extensionHostingGroup)->hostingBrons($extensionHostingGroup)->createOne();
        $this->extensionProduct = ProductFactory::new()->for($extensionGroup)->createOne([
            'name' => '.nl',
            'slug' => 'extension_nl',
        ]);

        $this->pleskSubscription = SubscriptionFactory::new()
            ->for($this->customer)
            ->for($hostingProduct)
            ->technicalStatusOk()
            ->createOne([
                'domain' => null,
            ]);

        /** @var Server $server */
        $server = ServerFactory::new()->plesk()->createOne([
            'hostname' => self::TEST_PLESK_SERVER,
            'domain' => self::TEST_PLESK_SERVER,
            'name' => self::TEST_PLESK_SERVER,
        ])->fresh(); // fresh or else the "wasRecentlyCreated" won't match in the mocked "with" params
        $this->server = $server;

        $migratedSubscription = MigratedSubscriptionsFactory::new()->createOne(['reference_subscription_id' => 'sub_1337_1']);
        $this->pleskSubscription->migratedSubscriptions()->attach($migratedSubscription);

        $migratedCustomer = MigratedCustomersFactory::new()->createOne();
        $migratedCustomer->customers()->attach($this->customer);
        $migratedCustomer->migratedSubscriptions()->attach($migratedSubscription);
        $migratedCustomer->refresh();

        $this->pleskSubscription->save();

        $placeholderProvider = ProviderFactory::new()->hostingPlaceholder()->createOne();
        ProviderFactory::new()->pleskHosting()->createOne();

        $this->pleskUsername = 'test_remote_username123';
        $this->pleskCustomerId = 132;

        HostingDeploymentFactory::new()
            ->for($this->pleskSubscription, 'subscription')
            ->for($placeholderProvider, 'provider')
            ->createOne([
                'plesk_customer_username' => null,
                'plesk_customer_id' => null,
                'directadmin_customer_username' => null,
                'server_id' => null,
            ]);

        $this->migratedSubscription = $migratedSubscription;
    }

    #[DataProvider('hostingMigrationJobProvider')]
    #[Test]
    public function hostingMigrationJob(
        string|null $originalSubscriptionDomain,
        string|null $defaultDomain,
        string $expectedSubscriptionDomain,
        bool $loginKeysSetting,
        bool $isUsingHostingServerAsNameserver,
        string|null $extensionAdministrativeStatus,
        bool $isReseller,
        string $packageName,
        bool $changeServicePlanCalled,
    ): void {
        if ($extensionAdministrativeStatus !== null) {
            SubscriptionFactory::new()
                ->for($this->extensionProduct)
                ->for($this->customer)
                ->administrativeStatus($extensionAdministrativeStatus)
                ->createOne([
                    'domain' => $originalSubscriptionDomain,
                ]);
        }

        if ($isReseller) {
            self::expectException(HostingMigrationIsResellerException::class);
        }

        $this->pleskSubscription->domain = $originalSubscriptionDomain;
        $this->pleskSubscription->save();

        $pleskHostingService = self::createMock(PleskHostingService::class);

        $pleskHostingService->method('isUsingHostingServerAsNameserver')
            ->willReturn($isUsingHostingServerAsNameserver);

        $pleskHostingService->method('getDefaultDomain')
            ->willReturn($defaultDomain);

        $pleskHostingService->method('getPackageOnServerAsDto')
            ->willReturn(new PleskHostingPackage(
                maxAmountDomains: 2,
                maxAmountMailAccounts: 5,
                maxAmountDatabases: 3,
                maxNetworkTrafficInMB: 1024,
                maxDiskSpaceInMB: 1024,
                package: 'basic',
            ));

        $pleskHostingService->method('getUserConfigAsDto')
            ->willReturn(
                new UserConfig(
                    dnscontrol: 'ON',
                    ssl: 'ON',
                    loginKeys: 'ON',
                    vdomains: '10',
                    nemails: '10',
                    mysql: '10',
                    bandwidth: '1024',
                    quota: '1024',
                    package: $packageName,
                    usertype: $isReseller ? HostingUserType::RESELLER : HostingUserType::USER,
                    domain: $defaultDomain,
                )
            );

        $pleskHostingService->expects($changeServicePlanCalled ? self::once() : self::never())
            ->method('changeServicePlan');

        $this->app->bind(PleskHostingService::class, fn (): PleskHostingService => $pleskHostingService);

        /** @var Collection<int, Subscription> $collection */
        $collection = new Collection($this->pleskSubscription);

        $payload = new HostingMigrationPayload(
            $collection,
            $this->migratedSubscription->reference_subscription_id ?? 'sub_1337_1',
            ProviderSlug::PLESK->value,
            $this->server->getDomain(),
            new PleskHostingDetails(
                pleskCustomerUsername: $this->pleskUsername,
                pleskCustomerId: $this->pleskCustomerId
            )
        );

        $ssoMock = self::createMock(HostingCanGenerateSSOAction::class);
        $ssoMock->expects(! $isReseller && $loginKeysSetting ? self::once() : self::never())
            ->method('execute')
            ->with(
                $this->pleskSubscription->refresh(),
                $this->pleskSubscription
                    ->customer()
                    ->firstOrFail()
                    ->migratedCustomers()
                    ->firstOrFail(),
                $payload,
                $this->server
            );

        $this->app->bind(HostingCanGenerateSSOAction::class, fn (): HostingCanGenerateSSOAction => $ssoMock);

        $job = new TechnicalHostingMigrationJob(
            $this->pleskSubscription->refresh(),
            TechnicalStatus::ERROR->value,
            $payload,
        );

        $adfService = self::resolve(AdfPayloadService::class);
        $dispatcher = self::resolve(Dispatcher::class);
        $logger = self::resolve(LoggerInterface::class);

        $job->handle($adfService, $dispatcher, $logger);
        $this->pleskSubscription->refresh();
        self::assertSame($expectedSubscriptionDomain, $this->pleskSubscription->domain);
    }

    /**
     * @return iterable<string, mixed>
     */
    public static function hostingMigrationJobProvider(): iterable
    {
        yield 'Set default domain when subscription domain is null + External domain + Internal nameservers + is not a reseller + package found' => [
            'originalSubscriptionDomain' => null,
            'defaultDomain' => 'default-domain.testing',
            'expectedSubscriptionDomain' => 'default-domain.testing',
            'loginKeysSetting' => true,
            'isUsingHostingServerAsNameserver' => true,
            'extensionAdministrativeStatus' => null,
            'isReseller' => false,
            'packageName' => 'hosting_brons',
            'changeServicePlanCalled' => false,
        ];

        yield 'Set default domain when subscription domain is null + External domain + Internal nameservers + is a reseller + package found' => [
            'originalSubscriptionDomain' => null,
            'defaultDomain' => 'default-domain.testing',
            'expectedSubscriptionDomain' => 'default-domain.testing',
            'loginKeysSetting' => true,
            'isUsingHostingServerAsNameserver' => true,
            'extensionAdministrativeStatus' => null,
            'isReseller' => true,
            'packageName' => 'hosting_brons',
            'changeServicePlanCalled' => false,
        ];

        yield 'Set default domain when subscription domain is null + External domain + Internal nameservers + SSO disabled + is not a reseller' => [
            'originalSubscriptionDomain' => null,
            'defaultDomain' => 'default-domain.testing',
            'expectedSubscriptionDomain' => 'default-domain.testing',
            'loginKeysSetting' => true,
            'isUsingHostingServerAsNameserver' => true,
            'extensionAdministrativeStatus' => null,
            'isReseller' => false,
            'packageName' => 'hosting_brons',
            'changeServicePlanCalled' => false,
        ];

        yield 'Subscription domain should not be overwritten + Internal domain + Internal nameservers + is not a reseller' => [
            'originalSubscriptionDomain' => 'already-set-domain.testing',
            'defaultDomain' => 'default-domain.testing',
            'expectedSubscriptionDomain' => 'already-set-domain.testing',
            'loginKeysSetting' => true,
            'isUsingHostingServerAsNameserver' => true,
            'extensionAdministrativeStatus' => AdministrativeStatus::ACTIVE->value,
            'isReseller' => false,
            'packageName' => 'web-mini',
            'changeServicePlanCalled' => true,
        ];

        yield 'Subscription domain should not be overwritten + Internal domain + Internal nameservers + is not a reseller + package is already the same' => [
            'originalSubscriptionDomain' => 'already-set-domain.testing',
            'defaultDomain' => 'default-domain.testing',
            'expectedSubscriptionDomain' => 'already-set-domain.testing',
            'loginKeysSetting' => true,
            'isUsingHostingServerAsNameserver' => true,
            'extensionAdministrativeStatus' => AdministrativeStatus::ACTIVE->value,
            'isReseller' => false,
            'packageName' => 'hosting_brons',
            'changeServicePlanCalled' => false,
        ];

        yield 'Subscription domain should not be overwritten + Internal Domain + External nameservers + is not a reseller' => [
            'originalSubscriptionDomain' => 'already-set-domain.testing',
            'defaultDomain' => 'default-domain.testing',
            'expectedSubscriptionDomain' => 'already-set-domain.testing',
            'loginKeysSetting' => true,
            'isUsingHostingServerAsNameserver' => false,
            'extensionAdministrativeStatus' => AdministrativeStatus::ACTIVE->value,
            'isReseller' => false,
            'packageName' => 'hosting_brons',
            'changeServicePlanCalled' => false,
        ];

        yield 'Subscription domain should not be overwritten + Internal domain (canceled) + Internal nameservers + is not a reseller' => [
            'originalSubscriptionDomain' => 'already-set-domain.testing',
            'defaultDomain' => 'default-domain.testing',
            'expectedSubscriptionDomain' => 'already-set-domain.testing',
            'loginKeysSetting' => true,
            'isUsingHostingServerAsNameserver' => true,
            'extensionAdministrativeStatus' => AdministrativeStatus::CANCELED->value,
            'isReseller' => false,
            'packageName' => 'hosting_brons',
            'changeServicePlanCalled' => false,
        ];

        yield 'Subscription domain should not be overwritten + Internal domain (expired) + Internal nameservers + is not a reseller' => [
            'originalSubscriptionDomain' => 'already-set-domain.testing',
            'defaultDomain' => 'default-domain.testing',
            'expectedSubscriptionDomain' => 'already-set-domain.testing',
            'loginKeysSetting' => true,
            'isUsingHostingServerAsNameserver' => true,
            'extensionAdministrativeStatus' => AdministrativeStatus::EXPIRED->value,
            'isReseller' => false,
            'packageName' => 'hosting_brons',
            'changeServicePlanCalled' => false,
        ];

        yield 'Subscription domain should not be overwritten + Internal domain (archived) + Internal nameservers + is not a reseller' => [
            'originalSubscriptionDomain' => 'already-set-domain.testing',
            'defaultDomain' => 'default-domain.testing',
            'expectedSubscriptionDomain' => 'already-set-domain.testing',
            'loginKeysSetting' => true,
            'isUsingHostingServerAsNameserver' => true,
            'extensionAdministrativeStatus' => AdministrativeStatus::ARCHIVED->value,
            'isReseller' => false,
            'packageName' => 'hosting_brons',
            'changeServicePlanCalled' => false,
        ];

        yield 'Set default domain when subscription domain is null and backend domain is null + External Domain + External nameservers + is not a reseller' => [
            'originalSubscriptionDomain' => null,
            'defaultDomain' => null,
            'expectedSubscriptionDomain' => 'test_remote_username123.test.plesk.test', // Fallback test
            'loginKeysSetting' => true,
            'isUsingHostingServerAsNameserver' => false,
            'extensionAdministrativeStatus' => AdministrativeStatus::ACTIVE->value,
            'isReseller' => false,
            'packageName' => 'hosting_brons',
            'changeServicePlanCalled' => false,
        ];

        yield 'Set default domain when subscription domain is null + External Domain + External nameservers + is not a reseller + package found' => [
            'originalSubscriptionDomain' => null,
            'defaultDomain' => 'default-domain.testing',
            'expectedSubscriptionDomain' => 'default-domain.testing',
            'loginKeysSetting' => true,
            'isUsingHostingServerAsNameserver' => false,
            'extensionAdministrativeStatus' => AdministrativeStatus::ACTIVE->value,
            'isReseller' => false,
            'packageName' => 'hosting_brons',
            'changeServicePlanCalled' => false,
        ];
    }

    #[Test]
    public function hostingMigrationJobRollback(): void
    {
        $exceptionMessage = 'testing error';
        $exceptionStatus  = 404;
        $pleskHostingService = self::createStub(PleskHostingService::class);
        $pleskHostingService->method('getDefaultDomain')
            ->willThrowException((new PleskClientException($exceptionMessage, $exceptionStatus)));

        $this->app->bind(PleskHostingService::class, fn (): PleskHostingService => $pleskHostingService);

        $this->pleskSubscription->domain = null;
        $this->pleskSubscription->save();

        /** @var Collection<int, Subscription> $collection */
        $collection = new Collection($this->pleskSubscription);

        $payload = new HostingMigrationPayload(
            $collection,
            $this->migratedSubscription->reference_subscription_id ?? 'sub_1337_1',
            ProviderSlug::PLESK->value,
            $this->server->getDomain(),
            new PleskHostingDetails(
                pleskCustomerUsername: $this->pleskUsername,
                pleskCustomerId: $this->pleskCustomerId,
            )
        );

        $job = new TechnicalHostingMigrationJob(
            $this->pleskSubscription,
            TechnicalStatus::ERROR->value,
            $payload,
        );

        $adfService = self::resolve(AdfPayloadService::class);
        $dispatcher = self::resolve(Dispatcher::class);
        $logger = self::resolve(LoggerInterface::class);

        $this->expectException(HostingUnableToSetDefaultDomainException::class);
        $this->expectExceptionMessageIs(
            sprintf(
                'Hosting default domain could not be set using server %s for subscription %d (payload: %s)',
                $this->server->hostname,
                $this->pleskSubscription->id,
                json_encode($payload->toArray(), JSON_THROW_ON_ERROR)
            )
        );

        $job->handle($adfService, $dispatcher, $logger);

        $freshSubscription = $this->pleskSubscription->refresh();
        self::assertNull($freshSubscription->domain);

        $hostingDeployment = $freshSubscription->hostingDeployment;
        self::assertInstanceOf(HostingDeployment::class, $hostingDeployment);
        self::assertNull($hostingDeployment->server);
        self::assertNull($hostingDeployment->plesk_customer_username);
        self::assertNull($hostingDeployment->plesk_customer_id);
        self::assertNull($hostingDeployment->directadmin_customer_username);
        self::assertSame(ProviderType::HOSTING, $hostingDeployment->provider?->type);
        self::assertSame(ProviderSlug::PLACEHOLDER, $hostingDeployment->provider->slug);
    }
}
