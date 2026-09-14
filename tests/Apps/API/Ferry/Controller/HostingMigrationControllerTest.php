<?php

declare(strict_types=1);

namespace Tests\Apps\API\Ferry\Controller;

use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\CoversClass;
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
use Waterfront\Apps\API\Ferry\Controllers\HostingMigrationController;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Hosting\DirectAdmin\Services\DirectAdminHostingService;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Hosting\Plesk\Services\PleskHostingService;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Servers\Enums\ServerType;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;

#[CoversClass(HostingMigrationController::class)]
class HostingMigrationControllerTest extends IntegrationTestCase
{
    private const string TEST_DOMAIN = 'test-domain.testing';

    private const string TEST_DIRECTADMIN_SERVER = '204.directadmin.test';

    private const string TEST_PLESK_SERVER = '204.plesk.test';

    private Customer $customer;

    private Subscription $directadminSubscription;

    private Subscription $pleskSubscription;

    private HostingDeployment $directadminHostingSubscription;

    private HostingDeployment $pleskHostingSubscription;

    private Provider $directadminProvider;

    private Provider $pleskProvider;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();

        $this->customer = CustomerFactory::new()->createOne();

        $extensionHostingGroup = ProductGroupFactory::new()->hosting()->createOne();

        $hostingProduct = ProductFactory::new()->hostingBrons($extensionHostingGroup)->createOne();

        $hostingProduct->load('productSpecs');

        $this->directadminSubscription = SubscriptionFactory::new()
            ->forDomain(self::TEST_DOMAIN)
            ->for($this->customer)
            ->for($hostingProduct)
            ->technicalStatusOk()
            ->createOne();

        $placeholderProvider = new ProviderFactory()->hostingPlaceholder()->createOne();

        $directadminServer = ServerFactory::new()->directadmin()->createOne([
            'hostname' => self::TEST_DIRECTADMIN_SERVER,
            'domain' => self::TEST_DIRECTADMIN_SERVER,
            'name' => self::TEST_DIRECTADMIN_SERVER,
        ]);

        $this->directadminHostingSubscription = HostingDeploymentFactory::new()->createOne([
            'server_id' => $directadminServer->id,
            'provider_id' => $placeholderProvider->id,
            'subscription_uuid' => $this->directadminSubscription->uuid,
            'plesk_customer_username' => null,
            'plesk_customer_id' => null,
        ]);

        $migratedSubscription = MigratedSubscriptionsFactory::new()->createOne([
            'reference_subscription_id' => 'sub_1337_1',
        ]);
        $this->directadminSubscription->migratedSubscriptions()->attach($migratedSubscription);

        $migratedSubscription2 = MigratedSubscriptionsFactory::new()->createOne([
            'reference_subscription_id' => 'sub_redirect_1337',
        ]);
        $this->directadminSubscription->migratedSubscriptions()->attach($migratedSubscription2);

        $migrationCustomer = MigratedCustomersFactory::new()->createOne();
        $migrationCustomer->migratedSubscriptions()->attach($migratedSubscription);
        $migrationCustomer->migratedSubscriptions()->attach($migratedSubscription2);
        $migrationCustomer->customers()->attach($this->customer);

        $this->directadminSubscription->save();

        $this->pleskSubscription = SubscriptionFactory::new()
            ->forDomain(self::TEST_DOMAIN)
            ->for($this->customer)
            ->for($hostingProduct)
            ->administrativeStatusCancelled()
            ->technicalStatusOk()
            ->createOne();

        $pleskServer = ServerFactory::new()->plesk()->createOne([
            'hostname' => self::TEST_PLESK_SERVER,
            'domain' => self::TEST_PLESK_SERVER,
            'name' => self::TEST_PLESK_SERVER,
        ]);

        $this->pleskHostingSubscription = HostingDeploymentFactory::new()->createOne([
            'server_id' => $pleskServer->id,
            'provider_id' => $placeholderProvider->id,
            'subscription_uuid' => $this->pleskSubscription->uuid,
            'directadmin_customer_username' => null,
            'plesk_customer_username' => null,
            'plesk_customer_id' => null,
        ]);

        $migratedSubscription2 = MigratedSubscriptionsFactory::new()->createOne([
            'reference_subscription_id' => 'sub_1337_2',
        ]);
        $this->pleskSubscription->migratedSubscriptions()->attach($migratedSubscription2);

        $migrationCustomer2 = MigratedCustomersFactory::new()->createOne();
        $migrationCustomer2->migratedSubscriptions()->attach($migratedSubscription2);

        $this->pleskSubscription->save();

        $this->pleskProvider = ProviderFactory::new()->pleskHosting()->createOne(['default' => true]);
        $this->directadminProvider = ProviderFactory::new()->hostingDirectAdmin()->createOne(['default' => true]);

        $mockHostingService = self::createStub(PleskHostingService::class);
        $mockHostingService->method('isUsingHostingServerAsNameserver')->willReturn(true);
        $this->app->bind(PleskHostingService::class, fn () => $mockHostingService);

        $mockHostingService = self::createStub(DirectAdminHostingService::class);
        $mockHostingService->method('isUsingHostingServerAsNameserver')->willReturn(true);
        $this->app->bind(DirectAdminHostingService::class, fn () => $mockHostingService);
    }

    #[Test]
    public function migrateHostingSubscriptions(): void
    {
        $postData = include __DIR__ . '/data/hosting_migration.php';

        $this->actingAsSystem()
            ->postJson(
                $this->generateRoute('ferry.customers.subscriptions.migrate_hosting', [
                    'customer' => $this->customer->id,
                ]),
                $postData,
                [
                    'Authorization' => 'Bearer ferry_testing_api_key',
                    'X-Requested-With' => 'XMLHttpRequest',
                ],
            )
            ->assertStatus(Response::HTTP_MULTI_STATUS)
            ->assertExactJson([
                'failures' => [],
                'success' => [
                    [
                        'message' => 'Created jobs to migrate hosting for every eligible subscription',
                        'parameters' => [
                            'customerId' => $this->customer->id,
                            'subscriptionIds' => implode(',', [
                                $this->directadminSubscription->id,
                                $this->pleskSubscription->id,
                            ]),
                        ],
                        'baseParameters' => [],
                    ],
                ],
            ]);

        /**
         * DIRECTADMIN.
         */
        $this->directadminSubscription->refresh();

        self::assertSame(AdministrativeStatus::ACTIVE->value, $this->directadminSubscription->administrative_status);
        self::assertSame(TechnicalStatus::OK->value, $this->directadminSubscription->technical_status);

        $hostingDeployment = $this->directadminSubscription->hostingDeployment;
        self::assertInstanceOf(HostingDeployment::class, $hostingDeployment);

        $provider = $hostingDeployment->provider;
        self::assertInstanceOf(Provider::class, $provider);
        self::assertSame(ProviderType::HOSTING, $provider->type);
        self::assertSame(ProviderSlug::DIRECTADMIN, $provider->slug);
        self::assertSame('da1230', $hostingDeployment->directadmin_customer_username);
        self::assertNull($hostingDeployment->plesk_customer_id);
        self::assertNull($hostingDeployment->plesk_customer_username);

        $server = $hostingDeployment->server;
        self::assertInstanceOf(Server::class, $server);
        self::assertSame(ServerType::DIRECTADMIN, $server->type);
        self::assertSame(self::TEST_DIRECTADMIN_SERVER, $server->hostname);

        /**
         * Plesk.
         */
        $this->pleskSubscription->refresh();

        self::assertSame(AdministrativeStatus::CANCELED->value, $this->pleskSubscription->administrative_status);
        self::assertSame(TechnicalStatus::OK->value, $this->pleskSubscription->technical_status);

        $hostingDeployment = $this->pleskSubscription->hostingDeployment;
        self::assertInstanceOf(HostingDeployment::class, $hostingDeployment);

        $provider = $hostingDeployment->provider;
        self::assertInstanceOf(Provider::class, $provider);
        self::assertSame(ProviderType::HOSTING, $provider->type);
        self::assertSame(ProviderSlug::PLESK, $provider->slug);
        self::assertSame('plesk123', $hostingDeployment->plesk_customer_username);
        self::assertNull($hostingDeployment->plesk_customer_id);
        self::assertNull($hostingDeployment->directadmin_customer_username);

        $server = $hostingDeployment->server;
        self::assertInstanceOf(Server::class, $server);
        self::assertSame(ServerType::PLESK, $server->type);
        self::assertSame(self::TEST_PLESK_SERVER, $server->hostname);

        self::assertSame([], Invoice::all()->toArray(), 'Technical migration should not create any invoices');
    }

    #[Test]
    public function migrateInvalidRequest(): void
    {
        $postData = include __DIR__ . '/data/hosting_migration_invalid_request.php';

        $this->actingAsSystem()
            ->postJson(
                $this->generateRoute('ferry.customers.subscriptions.migrate_hosting', [
                    'customer' => $this->customer->id,
                ]),
                $postData,
                [
                    'Authorization' => 'Bearer ferry_testing_api_key',
                    'X-Requested-With' => 'XMLHttpRequest',
                ],
            )
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
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

    #[Test]
    public function migrateInvalidDriver(): void
    {
        $postData = include __DIR__ . '/data/hosting_migration_invalid_driver.php';

        $this->actingAsSystem()
            ->postJson(
                $this->generateRoute('ferry.customers.subscriptions.migrate_hosting', [
                    'customer' => $this->customer->id,
                ]),
                $postData,
                [
                    'Authorization' => 'Bearer ferry_testing_api_key',
                    'X-Requested-With' => 'XMLHttpRequest',
                ],
            )
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertExactJson([
                'message' => 'Het geselecteerde veld is ongeldig. (and 1 more error)',
                'errors' => [
                    '0.driver' => [
                        'Het geselecteerde veld is ongeldig.',
                    ],
                    '1.driver' => [
                        'Het geselecteerde veld is ongeldig.',
                    ],
                ],
            ]);
    }

    #[Test]
    public function migrateInvalidHostingSubscriptions(): void
    {
        $postData = include __DIR__ . '/data/hosting_migration.php';

        $this->directadminHostingSubscription->provider()->associate($this->directadminProvider);
        $this->directadminHostingSubscription->save();

        $this->pleskHostingSubscription->provider()->associate($this->pleskProvider);
        $this->pleskHostingSubscription->save();

        $this->actingAsSystem()
            ->postJson(
                $this->generateRoute('ferry.customers.subscriptions.migrate_hosting', [
                    'customer' => $this->customer->id,
                ]),
                $postData,
                [
                    'Authorization' => 'Bearer ferry_testing_api_key',
                    'X-Requested-With' => 'XMLHttpRequest',
                ],
            )
            ->assertStatus(Response::HTTP_MULTI_STATUS)
            ->assertExactJson([
                'failures' => [
                    [
                        'baseParameters' => [],
                        'message' => 'Hosting migration step not allowed for subscription: The hosting provider for this subscription (directadmin) is not eligible for this kind of migration',
                        'parameters' => [
                            'customerId' => $this->customer->id,
                            'subscriptionId' => $this->directadminSubscription->id,
                        ],
                    ],
                    [
                        'baseParameters' => [],
                        'message' => 'Hosting migration step not allowed for subscription: The hosting provider for this subscription (integratedservice) is not eligible for this kind of migration',
                        'parameters' => [
                            'customerId' => $this->customer->id,
                            'subscriptionId' => $this->pleskSubscription->id,
                        ],
                    ],
                ],
                'success' => [],
            ]);
    }
}
