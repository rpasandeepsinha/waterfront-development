<?php

declare(strict_types=1);

namespace Tests\Domain\Ferry\Jobs;

use Exception;
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
use Waterfront\Domain\Ferry\Actions\Hosting\HostingModifySiteForMigrationAction;
use Waterfront\Domain\Ferry\Dto\Hosting\DirectAdminHostingDetails;
use Waterfront\Domain\Ferry\Dto\Hosting\HostingMigrationPayload;
use Waterfront\Domain\Ferry\Exceptions\HostingMigrationIsResellerException;
use Waterfront\Domain\Ferry\Exceptions\HostingUnableToModifySettingsException;
use Waterfront\Domain\Ferry\Exceptions\HostingUnableToSetDefaultDomainException;
use Waterfront\Domain\Ferry\Jobs\TechnicalHostingMigrationJob;
use Waterfront\Domain\Ferry\Services\AdfPayloadService;
use Waterfront\Domain\Hosting\DirectAdmin\Services\DirectAdminHostingService;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Parameters;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Hosting\Services\HostingService;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\DirectAdminClient\DTO\DirectAdminUserPackage;
use Waterfront\Infra\DirectAdminClient\DTO\UserConfig;
use Waterfront\Infra\DirectAdminClient\Enums\HostingUserType;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminCommandException;

#[CoversClass(TechnicalHostingMigrationJob::class)]
#[AllowMockObjectsWithoutExpectations]
class TechnicalHostingMigrationJobDirectAdminTest extends IntegrationTestCase
{
    private const string TEST_DIRECTADMIN_SERVER = '204.directadmin.test';

    private Subscription $directadminSubscription;

    private MigratedSubscription $migratedSubscription;

    private Product $extensionProduct;

    private Customer $customer;

    private string $directAdminUsername;

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

        $this->directadminSubscription = SubscriptionFactory::new()
            ->for($this->customer)
            ->for($hostingProduct)
            ->technicalStatusOk()
            ->createOne([
                'domain' => null,
            ]);

        /** @var Server $server */
        $server = ServerFactory::new()->directadmin()->createOne([
            'hostname' => self::TEST_DIRECTADMIN_SERVER,
            'domain' => self::TEST_DIRECTADMIN_SERVER,
            'name' => self::TEST_DIRECTADMIN_SERVER,
        ])->fresh(); // fresh or else the "wasRecentlyCreated" won't match in the mocked "with" params
        $this->server = $server;

        $migratedSubscription = MigratedSubscriptionsFactory::new()->createOne(['reference_subscription_id' => 'sub_1337_1']);
        $this->directadminSubscription->migratedSubscriptions()->attach($migratedSubscription);

        $migratedCustomer = MigratedCustomersFactory::new()->createOne();
        $migratedCustomer->customers()->attach($this->customer);
        $migratedCustomer->migratedSubscriptions()->attach($migratedSubscription);
        $migratedCustomer->refresh();

        $this->directadminSubscription->save();

        $placeholderProvider = ProviderFactory::new()->hostingPlaceholder()->createOne();
        ProviderFactory::new()->hostingDirectAdmin()->createOne(['default' => true]);

        $this->directAdminUsername = 'test_remote_username123';

        HostingDeploymentFactory::new()
            ->for($this->directadminSubscription, 'subscription')
            ->for($placeholderProvider, 'provider')
            ->createOne([
                'directadmin_customer_username' => null,
                'plesk_customer_username' => null,
                'plesk_customer_id' => null,
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
        bool $desiredDNSSetting,
        bool $loginKeysSetting,
        bool $isUsingHostingServerAsNameserver,
        string|null $extensionAdministrativeStatus,
        bool $isReseller,
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

        $this->directadminSubscription->domain = $originalSubscriptionDomain;
        $this->directadminSubscription->save();

        $daHostingService = self::createStub(DirectAdminHostingService::class);

        $daHostingService->method('getDefaultDomain')
            ->willReturn($defaultDomain);

        $daHostingService->method('getPackageOnServerAsDto')
            ->willReturn(new DirectAdminUserPackage(
                vdomains: '2',
                nemails: '5',
                mysql: '3',
                bandwidth: '1024',
                quota: '1024',
                package: 'basic',
            ));

        $daHostingService->method('getUserConfigAsDto')
            ->willReturn(
                new UserConfig(
                    dnscontrol: $desiredDNSSetting ? 'ON' : 'OFF',
                    ssl: 'ON',
                    loginKeys: $loginKeysSetting ? 'ON' : 'OFF',
                    vdomains: '10',
                    nemails: '10',
                    mysql: '10',
                    bandwidth: '1024',
                    quota: '1024',
                    package: 'basic',
                    usertype: $isReseller ? HostingUserType::RESELLER : HostingUserType::USER,
                    domain: $defaultDomain,
                )
            );

        $daHostingService
            ->method('isUsingHostingServerAsNameserver')
            ->willReturn($isUsingHostingServerAsNameserver);

        $daHostingService->method('modifyCustomer')->willReturn(true);

        $this->app->bind(DirectAdminHostingService::class, fn (): DirectAdminHostingService => $daHostingService);

        /** @var Collection<int, Subscription> $collection */
        $collection = new Collection($this->directadminSubscription);

        $payload = new HostingMigrationPayload(
            $collection,
            $this->migratedSubscription->reference_subscription_id ?? 'sub_1337_1',
            ProviderSlug::DIRECTADMIN->value,
            $this->server->getDomain(),
            new DirectAdminHostingDetails($this->directAdminUsername)
        );

        $expectedParams = new Parameters();
        $expectedParams->setEnableDns($desiredDNSSetting);
        $expectedParams->setEnableLoginKeys(true);

        $directAdminSubscription = $this->directadminSubscription;
        $directAdminSubscription->refresh();

        $hostingService = self::createMock(HostingService::class);
        $hostingService
            ->expects(self::atMost(1))
            ->method('modifyCustomer')
            ->with(
                self::assertCallbackIsModel($directAdminSubscription),
                self::callback(
                    function (Parameters $params) use ($expectedParams) {
                        self::assertSame($expectedParams->getEnableDns(), $params->getEnableDns(), 'DNS setting was not the same in hosting modify');
                        self::assertSame($expectedParams->getEnableLoginKeys(), $params->getEnableLoginKeys(), 'login keys setting was not the same in hosting modify');

                        return true;
                    }
                )
            );

        // Mocking the deeper laying HostingService so that we also are sure the HostingParams here
        // get properly tested
        $this->app->bind(
            HostingModifySiteForMigrationAction::class,
            fn (): HostingModifySiteForMigrationAction =>
                new HostingModifySiteForMigrationAction(
                    $hostingService,
                    self::resolve(LoggerInterface::class)
                )
        );

        $ssoMock = self::createMock(HostingCanGenerateSSOAction::class);
        $ssoMock->expects($loginKeysSetting ? self::once() : self::never())
            ->method('execute')
            ->with(
                $this->directadminSubscription->refresh(),
                $this->directadminSubscription
                    ->customer()
                    ->firstOrFail()
                    ->migratedCustomers()
                    ->firstOrFail(),
                $payload,
                $this->server
            );

        $this->app->bind(HostingCanGenerateSSOAction::class, fn (): HostingCanGenerateSSOAction => $ssoMock);

        $job = new TechnicalHostingMigrationJob(
            $this->directadminSubscription->refresh(),
            TechnicalStatus::ERROR->value,
            $payload,
        );

        $adfService = self::resolve(AdfPayloadService::class);
        $dispatcher = self::resolve(Dispatcher::class);
        $logger = self::resolve(LoggerInterface::class);

        $job->handle($adfService, $dispatcher, $logger);

        self::assertSame($expectedSubscriptionDomain, $this->directadminSubscription->domain);
    }

    /**
     * @return iterable<string, mixed>
     */
    public static function hostingMigrationJobProvider(): iterable
    {
        yield 'Set default domain when subscription domain is null + External domain + Internal nameservers + is not a reseller' => [
            'originalSubscriptionDomain' => null,
            'defaultDomain' => 'default-domain.testing',
            'expectedSubscriptionDomain' => 'default-domain.testing',
            'desiredDNSSetting' => true,
            'loginKeysSetting' => true,
            'isUsingHostingServerAsNameserver' => true,
            'extensionAdministrativeStatus' => null,
            'isReseller' => false,
        ];

        yield 'Set default domain when subscription domain is null + External domain + Internal nameservers + is a reseller' => [
            'originalSubscriptionDomain' => null,
            'defaultDomain' => 'default-domain.testing',
            'expectedSubscriptionDomain' => 'default-domain.testing',
            'desiredDNSSetting' => true,
            'loginKeysSetting' => false,
            'isUsingHostingServerAsNameserver' => true,
            'extensionAdministrativeStatus' => null,
            'isReseller' => true,
        ];

        yield 'Set default domain when subscription domain is null + External domain + Internal nameservers + SSO disabled + is not a reseller' => [
            'originalSubscriptionDomain' => null,
            'defaultDomain' => 'default-domain.testing',
            'expectedSubscriptionDomain' => 'default-domain.testing',
            'desiredDNSSetting' => true,
            'loginKeysSetting' => false,
            'isUsingHostingServerAsNameserver' => true,
            'extensionAdministrativeStatus' => null,
            'isReseller' => false,
        ];

        yield 'Subscription domain should not be overwritten + Internal domain + Internal nameservers + is not a reseller' => [
            'originalSubscriptionDomain' => 'already-set-domain.testing',
            'defaultDomain' => 'default-domain.testing',
            'expectedSubscriptionDomain' => 'already-set-domain.testing',
            'desiredDNSSetting' => false,
            'loginKeysSetting' => true,
            'isUsingHostingServerAsNameserver' => true,
            'extensionAdministrativeStatus' => AdministrativeStatus::ACTIVE->value,
            'isReseller' => false,
        ];

        yield 'Subscription domain should not be overwritten + Internal Domain + External nameservers + is not a reseller' => [
            'originalSubscriptionDomain' => 'already-set-domain.testing',
            'defaultDomain' => 'default-domain.testing',
            'expectedSubscriptionDomain' => 'already-set-domain.testing',
            'desiredDNSSetting' => false,
            'loginKeysSetting' => true,
            'isUsingHostingServerAsNameserver' => false,
            'extensionAdministrativeStatus' => AdministrativeStatus::ACTIVE->value,
            'isReseller' => false,
        ];

        yield 'Subscription domain should not be overwritten + Internal domain (canceled) + Internal nameservers + is not a reseller' => [
            'originalSubscriptionDomain' => 'already-set-domain.testing',
            'defaultDomain' => 'default-domain.testing',
            'expectedSubscriptionDomain' => 'already-set-domain.testing',
            'desiredDNSSetting' => false,
            'loginKeysSetting' => true,
            'isUsingHostingServerAsNameserver' => true,
            'extensionAdministrativeStatus' => AdministrativeStatus::CANCELED->value,
            'isReseller' => false,
        ];

        yield 'Subscription domain should not be overwritten + Internal domain (expired) + Internal nameservers + is not a reseller' => [
            'originalSubscriptionDomain' => 'already-set-domain.testing',
            'defaultDomain' => 'default-domain.testing',
            'expectedSubscriptionDomain' => 'already-set-domain.testing',
            'desiredDNSSetting' => true,
            'loginKeysSetting' => true,
            'isUsingHostingServerAsNameserver' => true,
            'extensionAdministrativeStatus' => AdministrativeStatus::EXPIRED->value,
            'isReseller' => false,
        ];

        yield 'Subscription domain should not be overwritten + Internal domain (archived) + Internal nameservers + is not a reseller' => [
            'originalSubscriptionDomain' => 'already-set-domain.testing',
            'defaultDomain' => 'default-domain.testing',
            'expectedSubscriptionDomain' => 'already-set-domain.testing',
            'desiredDNSSetting' => true,
            'loginKeysSetting' => true,
            'isUsingHostingServerAsNameserver' => true,
            'extensionAdministrativeStatus' => AdministrativeStatus::ARCHIVED->value,
            'isReseller' => false,
        ];

        yield 'Set default domain when subscription domain is null and backend domain is null + External Domain + External nameservers + is not a reseller' => [
            'originalSubscriptionDomain' => null,
            'defaultDomain' => null,
            'expectedSubscriptionDomain' => 'test_remote_username123.204.directadmin.test', // Fallback test
            'desiredDNSSetting' => false,
            'loginKeysSetting' => true,
            'isUsingHostingServerAsNameserver' => false,
            'extensionAdministrativeStatus' => AdministrativeStatus::ACTIVE->value,
            'isReseller' => false,
        ];

        yield 'Set default domain when subscription domain is null + External Domain + External nameservers + is not a reseller' => [
            'originalSubscriptionDomain' => null,
            'defaultDomain' => 'default-domain.testing',
            'expectedSubscriptionDomain' => 'default-domain.testing',
            'desiredDNSSetting' => false,
            'loginKeysSetting' => true,
            'isUsingHostingServerAsNameserver' => false,
            'extensionAdministrativeStatus' => AdministrativeStatus::ACTIVE->value,
            'isReseller' => false,
        ];
    }

    #[Test]
    public function hostingMigrationJobRollback(): void
    {
        $exceptionMessage = 'testing error';
        $exceptionStatus  = 404;
        $daHostingService = self::createStub(DirectAdminHostingService::class);
        $daHostingService->method('getDefaultDomain')
            ->willThrowException((new DirectAdminCommandException($exceptionMessage, $exceptionStatus)));

        $this->app->bind(DirectAdminHostingService::class, fn (): DirectAdminHostingService => $daHostingService);

        $this->directadminSubscription->domain = null;
        $this->directadminSubscription->save();

        /** @var Collection<int, Subscription> $collection */
        $collection = new Collection($this->directadminSubscription);

        $payload = new HostingMigrationPayload(
            $collection,
            $this->migratedSubscription->reference_subscription_id ?? 'sub_1337_1',
            ProviderSlug::DIRECTADMIN->value,
            $this->server->getDomain(),
            new DirectAdminHostingDetails($this->directAdminUsername)
        );

        $job = new TechnicalHostingMigrationJob(
            $this->directadminSubscription,
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
                $this->directadminSubscription->id,
                json_encode($payload->toArray(), JSON_THROW_ON_ERROR)
            )
        );

        $job->handle($adfService, $dispatcher, $logger);

        $freshSubscription = $this->directadminSubscription->refresh();
        self::assertNull($freshSubscription->domain);

        $hostingDeployment = $freshSubscription->hostingDeployment;
        self::assertInstanceOf(HostingDeployment::class, $hostingDeployment);
        self::assertNull($hostingDeployment->server);
        self::assertNull($hostingDeployment->directadmin_customer_username);
        self::assertSame(ProviderType::HOSTING, $hostingDeployment->provider?->type);
        self::assertSame(ProviderSlug::PLACEHOLDER, $hostingDeployment->provider->slug);
    }

    #[Test]
    public function hostingMigrationJobThrowsHostingUnableToChangeDnsManagementSettingException(): void
    {
        $exceptionMessage = sprintf(
            'Unable to change DNS management to {0} and SSO setting to {1} the hosting backend for subscription ID: {%d}',
            $this->directadminSubscription->id,
        );
        $daHostingService = self::createStub(DirectAdminHostingService::class);
        $daHostingService->method('modifyCustomer')
            ->willThrowException(new Exception('something unforeseen'));

        $this->app->bind(DirectAdminHostingService::class, fn (): DirectAdminHostingService => $daHostingService);

        $this->directadminSubscription->domain = null;
        $this->directadminSubscription->save();

        /** @var Collection<int, Subscription> $collection */
        $collection = new Collection($this->directadminSubscription);

        $payload = new HostingMigrationPayload(
            $collection,
            $this->migratedSubscription->reference_subscription_id ?? 'sub_1337_1',
            ProviderSlug::DIRECTADMIN->value,
            $this->server->getDomain(),
            new DirectAdminHostingDetails($this->directAdminUsername)
        );

        $job = new TechnicalHostingMigrationJob(
            $this->directadminSubscription,
            TechnicalStatus::ERROR->value,
            $payload,
        );

        $adfService = self::resolve(AdfPayloadService::class);
        $dispatcher = self::resolve(Dispatcher::class);
        $logger = self::resolve(LoggerInterface::class);

        $this->expectException(HostingUnableToModifySettingsException::class);
        $this->expectExceptionMessageIs($exceptionMessage);

        $job->handle($adfService, $dispatcher, $logger);

        $freshSubscription = $this->directadminSubscription->refresh();
        self::assertNull($freshSubscription->domain);

        $hostingDeployment = $freshSubscription->hostingDeployment;
        self::assertInstanceOf(HostingDeployment::class, $hostingDeployment);
        self::assertNull($hostingDeployment->server);
        self::assertNull($hostingDeployment->directadmin_customer_username);
        self::assertSame(ProviderType::HOSTING, $hostingDeployment->provider?->type);
        self::assertSame(ProviderSlug::PLACEHOLDER, $hostingDeployment->provider->slug);
    }
}
