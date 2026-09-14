<?php

declare(strict_types=1);

namespace Tests\Apps\API\Ferry\Controller\Bulk;

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
use Waterfront\Apps\API\Ferry\Controllers\Bulk\HostingBulkMigrationController;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Ferry\Actions\Hosting\ExecuteTechnicalHostingMigrationAction;

#[CoversClass(HostingBulkMigrationController::class)]
class HostingBulkMigrationControllerTest extends IntegrationTestCase
{
    private const string TEST_DOMAIN = 'test-domain.testing';
    private const string TEST_REDIRECT_DOMAIN = 'test-redirect-domain.testing';

    private const string TEST_DIRECTADMIN_SERVER = '204.directadmin.test';

    private const string TEST_PLESK_SERVER = '204.plesk.test';

    private Customer $customer;

    private Customer $customer2;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();

        $this->customer = CustomerFactory::new()->createOne();
        $this->customer2 = CustomerFactory::new()->createOne();

        $extensionHostingGroup = ProductGroupFactory::new()->hosting()->createOne();

        $hostingProduct = ProductFactory::new()->hostingBrons($extensionHostingGroup)->createOne();

        $redirectProduct = ProductFactory::new()->redirect($extensionHostingGroup)->createOne();

        $hostingProduct->load('productSpecs');

        $directadminSubscription = SubscriptionFactory::new()
            ->forDomain(self::TEST_DOMAIN)
            ->for($this->customer)
            ->for($hostingProduct)
            ->technicalStatusOk()
            ->createOne();

        $redirectSubscription = SubscriptionFactory::new()
            ->forDomain(self::TEST_REDIRECT_DOMAIN)
            ->for($this->customer)
            ->for($redirectProduct)
            ->technicalStatusOk()
            ->createOne();

        $migratedSubscription = MigratedSubscriptionsFactory::new()->createOne([
            'reference_subscription_id' => 'sub_redirect_1337_1',
        ]);
        $redirectSubscription->migratedSubscriptions()->attach($migratedSubscription);

        $placeholderProvider = new ProviderFactory()->hostingPlaceholder()->createOne();

        $directadminServer = ServerFactory::new()->directadmin()->createOne([
            'hostname' => self::TEST_DIRECTADMIN_SERVER,
            'domain' => self::TEST_DIRECTADMIN_SERVER,
            'name' => self::TEST_DIRECTADMIN_SERVER,
        ]);

        HostingDeploymentFactory::new()->createOne([
            'server_id' => $directadminServer->id,
            'provider_id' => $placeholderProvider->id,
            'subscription_uuid' => $directadminSubscription->uuid,
        ]);

        // Intentionally breaking the redirect subscription to test filters as this type
        // should never have a deployment.
        HostingDeploymentFactory::new()->createOne([
            'server_id' => null,
            'provider_id' => $placeholderProvider->id,
            'subscription_uuid' => $redirectSubscription->uuid,
        ]);

        $migratedSubscription = MigratedSubscriptionsFactory::new()->createOne([
            'reference_subscription_id' => 'sub_1337_1',
        ]);
        $directadminSubscription->migratedSubscriptions()->attach($migratedSubscription);

        $migratedSubscription2 = MigratedSubscriptionsFactory::new()->createOne([
            'reference_subscription_id' => 'sub_redirect_1337',
        ]);
        $directadminSubscription->migratedSubscriptions()->attach($migratedSubscription2);

        $migrationCustomer = MigratedCustomersFactory::new()->createOne();
        $migrationCustomer2 = MigratedCustomersFactory::new()->createOne();
        $migrationCustomer->migratedSubscriptions()->attach($migratedSubscription);
        $migrationCustomer2->migratedSubscriptions()->attach($migratedSubscription2);
        $migrationCustomer->customers()->attach($this->customer);
        $migrationCustomer2->customers()->attach($this->customer2);

        $directadminSubscription->save();
        $redirectSubscription->save();

        $pleskSubscription = SubscriptionFactory::new()
            ->forDomain(self::TEST_DOMAIN)
            ->for($this->customer2)
            ->for($hostingProduct)
            ->technicalStatusOk()
            ->createOne();

        $pleskServer = ServerFactory::new()->plesk()->createOne([
            'hostname' => self::TEST_PLESK_SERVER,
            'domain' => self::TEST_PLESK_SERVER,
            'name' => self::TEST_PLESK_SERVER,
        ]);

        HostingDeploymentFactory::new()->createOne([
            'server_id' => $pleskServer->id,
            'provider_id' => $placeholderProvider->id,
            'subscription_uuid' => $pleskSubscription->uuid,
        ]);

        $migratedSubscription2 = MigratedSubscriptionsFactory::new()->createOne([
            'reference_subscription_id' => 'sub_1337_2',
        ]);
        $pleskSubscription->migratedSubscriptions()->attach($migratedSubscription2);

        $migrationCustomer2 = MigratedCustomersFactory::new()->createOne();
        $migrationCustomer2->migratedSubscriptions()->attach($migratedSubscription2);

        $pleskSubscription->save();

        ProviderFactory::new()->pleskHosting()->createOne(['default' => true]);
        ProviderFactory::new()->hostingDirectAdmin()->createOne(['default' => true]);
    }

    #[Test]
    public function thatBulkHostingMigrationsReturnSuccess(): void
    {
        $action = self::createMock(ExecuteTechnicalHostingMigrationAction::class);
        $action->expects(self::exactly(2))->method('execute');

        $this->app->instance(ExecuteTechnicalHostingMigrationAction::class, $action);

        $postData = include __DIR__ . '/data/hosting_migration_bulk.php';

        $postData[0]['waterfront_customer_id'] = $this->customer->id;
        $postData[1]['waterfront_customer_id'] = $this->customer2->id;

        $this->actingAsSystem()
            ->postJson(
                $this->generateRoute('ferry.customers.subscriptions.migrate_hosting.bulk'),
                $postData,
                [
                    'Authorization' => 'Bearer ferry_testing_api_key',
                    'X-Requested-With' => 'XMLHttpRequest',
                ],
            )
            ->assertStatus(Response::HTTP_MULTI_STATUS);
    }

    #[Test]
    public function thatBulkHostingMigrationsUnprocessable(): void
    {
        $this->actingAsSystem()
            ->postJson(
                $this->generateRoute('ferry.customers.subscriptions.migrate_hosting.bulk'),
                [['waterfront_customer_id' => 'not_a_customer_id']],
                [
                    'Authorization' => 'Bearer ferry_testing_api_key',
                    'X-Requested-With' => 'XMLHttpRequest',
                ],
            )
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertExactJson([
                'message' => 'Dit veld dient een geheel getal te zijn.',
                'errors' => [
                    '0.waterfront_customer_id' => [
                        'Dit veld dient een geheel getal te zijn.',
                    ],
                ],
            ]);
    }
}
