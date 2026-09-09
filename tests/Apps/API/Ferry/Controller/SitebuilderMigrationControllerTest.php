<?php

declare(strict_types=1);

namespace Tests\Apps\API\Ferry\Controller;

use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
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
use Waterfront\Apps\API\Ferry\Controllers\SitebuilderMigrationController;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Hosting\Actions\BaseKit\BaseKitGetSsoUrlAction;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Hosting\Plesk\Services\PleskHostingService;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Sitebuilder\Factories\SitebuilderServiceFactory;
use Waterfront\Domain\Provision\Sitebuilder\Results\BasekitSiteResult;
use Waterfront\Domain\Provision\Sitebuilder\Results\BasekitUserResult;
use Waterfront\Domain\Provision\Sitebuilder\Results\SitebuilderResult;
use Waterfront\Domain\Provision\Sitebuilder\Services\BasekitProvisionService;
use Waterfront\Domain\Provision\Sitebuilder\Services\SitebuilderProvisionService;
use Waterfront\Domain\Provision\Sitebuilder\Validators\BasekitValidator;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Sitebuilder\DTO\BaseKitSite;
use Waterfront\Domain\Sitebuilder\DTO\BaseKitUser;
use Waterfront\Domain\Sitebuilder\SitebuilderService;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;

#[CoversClass(SitebuilderMigrationController::class)]
class SitebuilderMigrationControllerTest extends IntegrationTestCase
{
    private const string TEST_DOMAIN_SITEBUILDER = 'test-domain.testing';

    private Customer $customer;

    private Subscription $subscriptionDirectAdmin;

    private Subscription $subscriptionPlesk;

    private HostingDeployment $hostingDeploymentDirectAdmin;

    private HostingDeployment $hostingDeploymentPlesk;

    private Server $baseKitServer;

    private Server $mailOnlyServerDirectAdmin;

    private Server $mailOnlyServerPlesk;

    private Provider $sitebuilderProvider;

    private Provider $mailOnlyProviderDirectAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();

        $this->customer = CustomerFactory::new()->createOne();

        $hostingGroup = ProductGroupFactory::new()->hosting()->createOne();

        $sitebuilderProduct = ProductFactory::new()->siteBuilder()->for($hostingGroup)->createOne();

        $this->baseKitServer = ServerFactory::new()
            ->sitebuilder()
            ->createOne([
                'hostname' => 'basekit.test',
                'domain' => 'basekit.test',
            ]);

        $this->mailOnlyServerDirectAdmin = ServerFactory::new()
            ->directadminMail()
            ->createOne([
                'hostname' => 'mail_server.directadmin.test',
                'domain' => 'mail_server.directadmin.test',
            ]);

        $this->mailOnlyServerPlesk = ServerFactory::new()
            ->plesk()
            ->createOne([
                'hostname' => 'mail_server.plesk.test',
                'domain' => 'mail_server.plesk.test',
            ]);

        $this->subscriptionDirectAdmin = SubscriptionFactory::new()
            ->for($this->customer)
            ->for($sitebuilderProduct)
            ->technicalStatusOk()
            ->createOne([
                'domain' => null,
            ]);

        $this->subscriptionPlesk = SubscriptionFactory::new()
            ->for($this->customer)
            ->for($sitebuilderProduct)
            ->administrativeStatusCancelled()
            ->technicalStatusOk()
            ->createOne([
                'domain' => null,
            ]);

        $mailOnlyPlaceholderProvider = ProviderFactory::new()->emailOnlyPlaceholder()->createOne();
        $sitebuilderPlaceholderProvider = ProviderFactory::new()->sitebuilderPlaceholder()->createOne();

        $this->mailOnlyProviderDirectAdmin = ProviderFactory::new()->emailOnlyDirectAdmin()->createOne();
        ProviderFactory::new()->pleskHosting()->createOne();
        $this->sitebuilderProvider = ProviderFactory::new()->siteBuilderBaseKit()->createOne();

        $migratedSubscriptionDirectAdmin = MigratedSubscriptionsFactory::new()->createOne(['reference_subscription_id' => 'sub_1337_1']);
        $this->subscriptionDirectAdmin->migratedSubscriptions()->attach($migratedSubscriptionDirectAdmin);

        $migratedSubscriptionPlesk = MigratedSubscriptionsFactory::new()->createOne(['reference_subscription_id' => 'sub_1337_2']);
        $this->subscriptionPlesk->migratedSubscriptions()->attach($migratedSubscriptionPlesk);

        $migrationCustomer = MigratedCustomersFactory::new()->createOne(['reference_name' => 'versio']);
        $migrationCustomer->migratedSubscriptions()->attach($migratedSubscriptionDirectAdmin);
        $migrationCustomer->migratedSubscriptions()->attach($migratedSubscriptionPlesk);
        $migrationCustomer->customers()->attach($this->customer);
        $this->subscriptionDirectAdmin->save();

        $this->hostingDeploymentDirectAdmin = HostingDeploymentFactory::new()
            ->for($this->subscriptionDirectAdmin)
            ->for($mailOnlyPlaceholderProvider, 'mailProvider')
            ->for($sitebuilderPlaceholderProvider, 'sitebuilderProvider')
            ->createOne([
                'server_id' => null,
                'provider_id' => null,
                'directadmin_customer_username' => null,
                'plesk_customer_username' => null,
                'plesk_customer_id' => null,
            ]);

        $this->hostingDeploymentPlesk = HostingDeploymentFactory::new()
            ->for($this->subscriptionPlesk)
            ->for($mailOnlyPlaceholderProvider, 'mailProvider')
            ->for($sitebuilderPlaceholderProvider, 'sitebuilderProvider')
            ->createOne([
                'server_id' => null,
                'provider_id' => null,
                'directadmin_customer_username' => null,
                'plesk_customer_username' => null,
                'plesk_customer_id' => null,
            ]);

        $mockSitebuilderService = self::createStub(SitebuilderService::class);
        $mockSitebuilderService->method('getSiteFromRef')
            ->willReturn(new BaseKitSite(
                id: 456,
                domain: self::TEST_DOMAIN_SITEBUILDER,
            ));

        $mockSitebuilderService->method('getUserFromRef')
            ->willReturn(new BaseKitUser(
                id: 123,
                email: 'test@email.test',
            ));

        $this->app->bind(SitebuilderService::class, fn () => $mockSitebuilderService);

        $mockSsoAction = self::createStub(BaseKitGetSsoUrlAction::class);
        $mockSsoAction->method('execute')
            ->willReturn('https://basekit.test/sso-test');
        $this->app->bind(BaseKitGetSsoUrlAction::class, fn () => $mockSsoAction);

        $pleskHostingService = self::createStub(PleskHostingService::class);

        $this->app->instance(PleskHostingService::class, $pleskHostingService);
    }

    #[DataProvider('emailGatewayRouteProvider')]
    #[Test]
    public function migrateSitebuilderSubscriptions(string $email, bool $shouldGoThroughGateway): void
    {
        $this->customer->update(['email' => $email]);

        $this->mockSitebuilderServiceForScenario($email, $shouldGoThroughGateway);
        if ($shouldGoThroughGateway) {
            $this->mockSitebuilderProvisionServiceForGatewayFlow();
            $this->hostingDeploymentDirectAdmin->sitebuilder_provider_id = null;
            $this->hostingDeploymentPlesk->sitebuilder_provider_id = null;
            $this->hostingDeploymentDirectAdmin->save();
            $this->hostingDeploymentPlesk->save();
        }

        $postData = include __DIR__ . '/data/sitebuilder_migration.php';

        $this->actingAsSystem()
            ->postJson(
                $this->generateRoute('ferry.customers.subscriptions.migrate_sitebuilder', ['customer' => $this->customer->id]),
                $postData,
                [
                    'Authorization' => 'Bearer ferry_testing_api_key',
                    'X-Requested-With' => 'XMLHttpRequest',
                ]
            )->assertStatus(Response::HTTP_MULTI_STATUS)
            ->assertExactJson([
                'failures' => [],
                'success' => [
                    [
                        'message' => 'Created jobs to migrate sitebuilder for every eligible subscription',
                        'baseParameters' => [],
                        'parameters' => [
                            'customerId' => $this->customer->id,
                            'subscriptionIds' => $this->subscriptionDirectAdmin->id . ',' . $this->subscriptionPlesk->id,
                        ],
                    ],
                ],
            ]);

        $this->subscriptionDirectAdmin->refresh();
        $this->subscriptionPlesk->refresh();
        $this->hostingDeploymentDirectAdmin->refresh();
        $this->hostingDeploymentPlesk->refresh();

        self::assertSame(AdministrativeStatus::ACTIVE->value, $this->subscriptionDirectAdmin->administrative_status);
        self::assertSame(TechnicalStatus::OK->value, $this->subscriptionDirectAdmin->technical_status);
        self::assertSame(AdministrativeStatus::CANCELED->value, $this->subscriptionPlesk->administrative_status);
        self::assertSame(TechnicalStatus::OK->value, $this->subscriptionPlesk->technical_status);

        self::assertNull($this->hostingDeploymentDirectAdmin->provider()->first());
        self::assertNull($this->hostingDeploymentPlesk->provider()->first());
        self::assertNull($this->hostingDeploymentDirectAdmin->server()->first());
        self::assertNull($this->hostingDeploymentPlesk->server()->first());

        self::assertSame([], Invoice::all()->toArray(), 'Technical migration should not create any invoices');

        if ($shouldGoThroughGateway) {
            // We use example.com here because .testing and .test domains are not accepted by the DomainNameRule
            self::assertSame('example.com', $this->subscriptionDirectAdmin->domain);

            self::assertNull($this->hostingDeploymentDirectAdmin->basekit_site_ref);
            self::assertNull($this->hostingDeploymentDirectAdmin->basekit_user_ref);
            self::assertNull($this->hostingDeploymentDirectAdmin->basekitServer);

            self::assertNull($this->hostingDeploymentPlesk->basekit_site_ref);
            self::assertNull($this->hostingDeploymentPlesk->basekit_user_ref);
            self::assertNull($this->hostingDeploymentPlesk->basekitServer);

            self::assertNull($this->hostingDeploymentDirectAdmin->sitebuilderProvider);
            self::assertNull($this->hostingDeploymentPlesk->sitebuilderProvider);
        } else {
            self::assertSame(self::TEST_DOMAIN_SITEBUILDER, $this->subscriptionDirectAdmin->domain);

            // Directadmin Variation
            // Check BaseKit
            $sitebuilderProvider = $this->hostingDeploymentDirectAdmin->sitebuilderProvider;
            self::assertInstanceOf(Provider::class, $sitebuilderProvider);
            self::assertSame(ProviderType::SITEBUILDER, $sitebuilderProvider->type);
            self::assertSame(ProviderSlug::BASEKIT, $sitebuilderProvider->slug);
            self::assertSame(123, $this->hostingDeploymentDirectAdmin->basekit_user_ref);
            self::assertSame(456, $this->hostingDeploymentDirectAdmin->basekit_site_ref);
            self::assertSame($this->baseKitServer->id, $this->hostingDeploymentDirectAdmin->basekitServer?->id);

            // Plesk Variation
            // Check BaseKit
            $sitebuilderProvider = $this->hostingDeploymentPlesk->sitebuilderProvider;
            self::assertInstanceOf(Provider::class, $sitebuilderProvider);
            self::assertSame(ProviderType::SITEBUILDER, $sitebuilderProvider->type);
            self::assertSame(ProviderSlug::BASEKIT, $sitebuilderProvider->slug);
            self::assertSame(789, $this->hostingDeploymentPlesk->basekit_user_ref);
            self::assertSame(777, $this->hostingDeploymentPlesk->basekit_site_ref);
            self::assertSame($this->baseKitServer->id, $this->hostingDeploymentPlesk->basekitServer?->id);
        }

        // Check mail only DirectAdmin
        $mailProvider = $this->hostingDeploymentDirectAdmin->mailProvider;
        self::assertInstanceOf(Provider::class, $mailProvider);
        self::assertSame(ProviderType::MAILONLY, $mailProvider->type);
        self::assertSame(ProviderSlug::DIRECTADMIN, $mailProvider->slug);
        self::assertSame('da_mail_1230', $this->hostingDeploymentDirectAdmin->directadmin_customer_username);
        self::assertNull($this->hostingDeploymentDirectAdmin->plesk_customer_username);
        self::assertNull($this->hostingDeploymentDirectAdmin->plesk_customer_id);
        self::assertSame($this->mailOnlyServerDirectAdmin->id, $this->hostingDeploymentDirectAdmin->mailOnlyServer?->id);
        self::assertNull($this->hostingDeploymentDirectAdmin->server_id);
        self::assertNull($this->hostingDeploymentDirectAdmin->provider_id);

        // Check mail only Plesk
        $mailProvider = $this->hostingDeploymentPlesk->mailProvider;
        self::assertInstanceOf(Provider::class, $mailProvider);
        self::assertSame(ProviderType::HOSTING, $mailProvider->type);
        self::assertSame(ProviderSlug::PLESK, $mailProvider->slug);
        self::assertSame('plesk_mail_1230', $this->hostingDeploymentPlesk->plesk_customer_username);
        self::assertNull($this->hostingDeploymentPlesk->plesk_customer_id);
        self::assertNull($this->hostingDeploymentPlesk->directadmin_customer_username);
        self::assertSame($this->mailOnlyServerPlesk->id, $this->hostingDeploymentPlesk->mailOnlyServer?->id);
        self::assertNull($this->hostingDeploymentPlesk->server_id);
        self::assertNull($this->hostingDeploymentPlesk->provider_id);
    }

    #[Test]
    public function subscriptionAlreadyHasProvider(): void
    {
        $this->hostingDeploymentDirectAdmin->mailProvider()->associate($this->mailOnlyProviderDirectAdmin);
        $this->hostingDeploymentDirectAdmin->sitebuilderProvider()->associate($this->sitebuilderProvider);
        $this->hostingDeploymentDirectAdmin->save();

        $postData = include __DIR__ . '/data/sitebuilder_migration.php';

        $this->actingAsSystem()
            ->postJson(
                $this->generateRoute('ferry.customers.subscriptions.migrate_sitebuilder', ['customer' => $this->customer->id]),
                $postData,
                [
                    'Authorization' => 'Bearer ferry_testing_api_key',
                    'X-Requested-With' => 'XMLHttpRequest',
                ]
            )->assertStatus(Response::HTTP_MULTI_STATUS)
            ->assertExactJson([
                'failures' => [
                    [
                        'baseParameters' => [],
                        'message' => 'Sitebuilder migration step not allowed for subscription: The mail only provider (directadmin) or the sitebuilder provider (basekit) for this subscription is not eligible for this kind of migration',
                        'parameters' => [
                            'customerId' => $this->customer->id,
                            'subscriptionId' => $this->subscriptionDirectAdmin->id,
                        ],
                    ],
                ],
                'success' => [
                    [
                        'baseParameters' => [],
                        'message' => 'Created jobs to migrate sitebuilder for every eligible subscription',
                        'parameters' => [
                            'customerId' => $this->customer->id,
                            'subscriptionIds' => (string) $this->subscriptionPlesk->id,
                        ],
                    ],
                ],
            ]);
    }

    /**
     * @return iterable<string, mixed>
     */
    public static function emailGatewayRouteProvider(): iterable
    {
        yield 'normal customer' => [
            'email' => 'customer@example.com',
            'shouldGoThroughGateway' => false,
        ];

        yield 'gateway customer' => [
            'email' => 'gatewaytest@sandwave.io',
            'shouldGoThroughGateway' => true,
        ];
    }

    private function mockSitebuilderServiceForScenario(string $email, bool $shouldGoThroughGateway): void
    {
        $sitebuilderService = self::createStub(SitebuilderService::class);

        $sitebuilderService
            ->method('hasSitebuilderThroughGateway')
            ->willReturn($shouldGoThroughGateway);

        $sitebuilderService->method('getSiteFromRef')
            ->willReturn(new BaseKitSite(
                id: 456,
                domain: self::TEST_DOMAIN_SITEBUILDER,
            ));

        $sitebuilderService->method('getUserFromRef')
            ->willReturn(new BaseKitUser(
                id: 123,
                email: $email,
            ));

        $this->app->instance(SitebuilderService::class, $sitebuilderService);
    }

    private function mockSitebuilderProvisionServiceForGatewayFlow(): void
    {
        $basekitValidator = self::resolve(BasekitValidator::class);

        $mockBasekitService = self::createStub(BasekitProvisionService::class);

        $mockBasekitService
            ->method('getBasekitSiteByRef')
            ->willReturnCallback(fn ($request) => new BasekitSiteResult(
                provisionData: $request,
                provisionStatus: ProvisionStatus::SUCCESS,
                siteRef: 456,
                domain: 'example.com',
            ));

        $mockBasekitService
            ->method('getBasekitUserByRef')
            ->willReturnCallback(fn ($request) => new BasekitUserResult(
                provisionData: $request,
                provisionStatus: ProvisionStatus::SUCCESS,
                userId: 123,
                email: 'gatewaytest@sandwave.io',
            ));

        $mockBasekitService
            ->method('createFromMigration')
            ->willReturnCallback(fn ($request) => new SitebuilderResult(
                provisionData: $request,
                provisionStatus: ProvisionStatus::SUCCESS,
            ));

        $mockBasekitService
            ->method('rollbackFromMigration')
            ->willReturnCallback(fn ($request) => new SitebuilderResult(
                provisionData: $request,
                provisionStatus: ProvisionStatus::SUCCESS,
            ));

        $sitebuilderServiceFactory = new SitebuilderServiceFactory(
            $basekitValidator,
            $mockBasekitService,
        );

        $this->app->bind(
            SitebuilderProvisionService::class,
            fn () => new SitebuilderProvisionService($sitebuilderServiceFactory)
        );
    }
}
