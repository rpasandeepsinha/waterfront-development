<?php

declare(strict_types=1);

namespace Tests\Apps\API\Ferry\Controller;

use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\Factories\CustomerFactory;
use Tests\Factories\MigratedCustomersFactory;
use Tests\Factories\MigratedSubscriptionsFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ResellerHostingDeploymentFactory;
use Tests\Factories\ServerFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Ferry\Controllers\ResellerHostingMigrationController;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Ferry\Actions\Hosting\HostingCanGenerateSSOAction;
use Waterfront\Domain\Hosting\Services\HostingService;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\ResellerHosting\Models\ResellerHostingDeployment;
use Waterfront\Domain\ResellerHosting\Services\ResellerHostingService;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\DirectAdminClient\DTO\UserConfig;
use Waterfront\Infra\DirectAdminClient\Enums\HostingUserType;

#[CoversClass(ResellerHostingMigrationController::class)]
class ResellerHostingMigrationControllerTest extends IntegrationTestCase
{
    private const string TEST_DOMAIN = 'test-domain.testing';

    private const string TEST_DIRECTADMIN_SERVER = '204.directadmin.test';

    private Customer $customer;

    private Subscription $directadminSubscription;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();

        $this->customer = CustomerFactory::new()->createOne();

        $resellerHostingGroup = ProductGroupFactory::new()->resellerHosting()->createOne();

        $resellerHostingProduct = ProductFactory::new()->hostingBrons($resellerHostingGroup)->createOne();

        $resellerHostingProduct->load('productSpecs');

        $this->directadminSubscription = SubscriptionFactory::new()
            ->forDomain(self::TEST_DOMAIN)
            ->for($this->customer)
            ->for($resellerHostingProduct)
            ->technicalStatusOk()
            ->createOne();

        $placeholderProvider = new ProviderFactory()->hostingPlaceholder()->createOne();

        $directadminServer = ServerFactory::new()->directadmin()->createOne([
            'hostname' => self::TEST_DIRECTADMIN_SERVER,
            'domain' => self::TEST_DIRECTADMIN_SERVER,
            'name' => self::TEST_DIRECTADMIN_SERVER,
        ]);

        ResellerHostingDeploymentFactory::new()->createOne([
            'server_id' => $directadminServer->id,
            'provider_id' => $placeholderProvider->id,
            'subscription_uuid' => $this->directadminSubscription->uuid,
        ]);

        $migratedSubscription = MigratedSubscriptionsFactory::new()->createOne(['reference_subscription_id' => 'sub_1337_1']);
        $this->directadminSubscription->migratedSubscriptions()->attach($migratedSubscription);

        $migratedSubscription2 = MigratedSubscriptionsFactory::new()->createOne(['reference_subscription_id' => 'sub_redirect_1337']);
        $this->directadminSubscription->migratedSubscriptions()->attach($migratedSubscription2);

        $migrationCustomer = MigratedCustomersFactory::new()->createOne();
        $migrationCustomer->migratedSubscriptions()->attach($migratedSubscription);
        $migrationCustomer->migratedSubscriptions()->attach($migratedSubscription2);
        $migrationCustomer->customers()->attach($this->customer);

        $this->directadminSubscription->save();

        ProviderFactory::new()->hostingDirectAdmin()->createOne(['default' => true]);

        $mockHostingService = self::createStub(HostingService::class);

        $mockHostingService->method('getUserConfigAsDto')
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
                    package: 'basic',
                    usertype: HostingUserType::RESELLER,
                )
            );

        $this->app->instance(HostingService::class, $mockHostingService);

        $ssoMock = self::createStub(HostingCanGenerateSSOAction::class);
        $ssoMock->method('execute');

        $this->app->bind(HostingCanGenerateSSOAction::class, fn (): HostingCanGenerateSSOAction => $ssoMock);

        $mockResellerHostingService = self::createMock(ResellerHostingService::class);
        $mockResellerHostingService
            ->expects(self::atMost(1))
            ->method('modifyCustomerForResellerMigrations')
            ->with(ProviderSlug::DIRECTADMIN, 'da1230')
            ->willReturn(true);

        $this->app->bind(ResellerHostingService::class, fn (): ResellerHostingService => $mockResellerHostingService);
    }

    #[Test]
    public function migrateResellerHostingSubscriptions(): void
    {
        $postData = include __DIR__ . '/data/reseller_hosting_migration.php';

        $this->actingAsSystem()
            ->postJson(
                $this->generateRoute('ferry.customers.subscriptions.migrate_reseller_hosting', ['customer' => $this->customer->id]),
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
                        'message' => 'Created jobs to migrate reseller hosting for every eligible subscription',
                        'parameters' => [
                            'customerId' => $this->customer->id,
                            'subscriptionIds' => implode(',', [
                                $this->directadminSubscription->id,
                            ]),
                        ],
                        'baseParameters' => [],
                    ],
                ],
            ]);

        $this->directadminSubscription->refresh();

        self::assertSame(AdministrativeStatus::ACTIVE->value, $this->directadminSubscription->administrative_status);
        self::assertSame(TechnicalStatus::OK->value, $this->directadminSubscription->technical_status);

        $resellerHostingDeployment = $this->directadminSubscription->resellerHostingDeployment;
        self::assertInstanceOf(ResellerHostingDeployment::class, $resellerHostingDeployment);
        $hostingDeployment = $this->directadminSubscription->hostingDeployment;
        self::assertNull($hostingDeployment);

        $provider = $resellerHostingDeployment->provider;
        self::assertSame(ProviderType::HOSTING, $provider->type);
        self::assertSame(ProviderSlug::DIRECTADMIN, $provider->slug);
        self::assertSame('da1230', $resellerHostingDeployment->directadmin_customer_username);

        self::assertSame([], Invoice::all()->toArray(), 'Technical migration should not create any invoices');
    }

    #[Test]
    public function migrateInvalidRequest(): void
    {
        $postData = include __DIR__ . '/data/hosting_migration_invalid_request.php';

        $this->actingAsSystem()
            ->postJson(
                $this->generateRoute('ferry.customers.subscriptions.migrate_hosting', ['customer' => $this->customer->id]),
                $postData,
                [
                    'Authorization' => 'Bearer ferry_testing_api_key',
                    'X-Requested-With' => 'XMLHttpRequest',
                ]
            )->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertExactJson([
                'message' => 'Het geselecteerde veld is ongeldig. (and 2 more errors)',
                'errors' => [
                    '0.hostname' => [
                        'Het geselecteerde veld is ongeldig.',
                    ],
                    '0.reference_subscription_id' => [
                        'Het geselecteerde veld is ongeldig.',
                    ],
                    '0.server_data.directadmin_customer_name' => [
                        'Dit veld is verplicht wanneer 0.driver is directadmin.',
                    ],
                ],
            ]);
    }
}
