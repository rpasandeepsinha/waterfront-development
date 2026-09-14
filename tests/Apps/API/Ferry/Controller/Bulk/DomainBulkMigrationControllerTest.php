<?php

declare(strict_types=1);

namespace Tests\Apps\API\Ferry\Controller\Bulk;

use Illuminate\Http\Client\Response as LaravelResponse;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\MigratedCustomersFactory;
use Tests\Factories\MigratedSubscriptionsFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Ferry\Controllers\Bulk\DomainBulkMigrationController;
use Waterfront\Domain\Ferry\Actions\Domains\ExecuteTechnicalDomainMigrationAction;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;

#[CoversClass(DomainBulkMigrationController::class)]
class DomainBulkMigrationControllerTest extends IntegrationTestCase
{
    #[Test]
    public function thatBulkCreateDomainMigrationsReturnSuccess(): void
    {
        Http::fake();
        Http::shouldReceive('post')
            ->twice()
            ->andReturn(new LaravelResponse(new \GuzzleHttp\Psr7\Response()));

        Http::shouldReceive('withHeaders')->twice()->andReturnSelf();

        $mock = self::createMock(ExecuteTechnicalDomainMigrationAction::class);
        $mock->expects(self::exactly(2))->method('execute');
        $this->app->instance(ExecuteTechnicalDomainMigrationAction::class, $mock);

        $json = (string) file_get_contents(__DIR__ . '/data/bulk_technical_domain_migration_payload.json');
        /** @var array<string, mixed> $payload */
        $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $placeholder = ProviderFactory::new()->domainPlaceholder()->createOne();
        $product = ProductFactory::new()->nlDomain()->createOne();

        $customerA = CustomerFactory::new()->createOne();
        $migratedCustomerA = MigratedCustomersFactory::new()->createOne();

        $migratedSubscriptionOne = MigratedSubscriptionsFactory::new()->createOne([
            'reference_subscription_id' => 'my_subscription_id',
        ]);

        $migratedSubscriptionTwo = MigratedSubscriptionsFactory::new()->createOne([
            'reference_subscription_id' => 'my_subscription_id_another_one',
        ]);

        $subscriptionOne = SubscriptionFactory::new()
            ->for($product)
            ->for($customerA)
            ->technicalStatusDomainActive()
            ->createOne();
        $subscriptionTwo = SubscriptionFactory::new()
            ->for($product)
            ->for($customerA)
            ->technicalStatus(TechnicalStatus::FAILED->value)
            ->createOne();

        DomainDeploymentFactory::new()->for($subscriptionOne)->for($placeholder)->createOne();
        DomainDeploymentFactory::new()->for($subscriptionTwo)->for($placeholder)->createOne();

        $migratedSubscriptionOne->subscriptions()->attach($subscriptionOne);
        $migratedSubscriptionTwo->subscriptions()->attach($subscriptionTwo);

        $migratedCustomerA->migratedSubscriptions()->attach($migratedSubscriptionOne);
        $migratedCustomerA->migratedSubscriptions()->attach($migratedSubscriptionTwo);
        $customerA->migratedCustomers()->attach($migratedCustomerA);

        $customerB = CustomerFactory::new()->createOne();
        $migratedCustomerB = MigratedCustomersFactory::new()->createOne();
        $migratedSubscriptionTree = MigratedSubscriptionsFactory::new()->createOne([
            'reference_subscription_id' => 'my_subscription_id_second',
        ]);
        $subscriptionTree = SubscriptionFactory::new()
            ->for($product)
            ->for($customerB)
            ->technicalStatusDomainActive()
            ->createOne();

        DomainDeploymentFactory::new()->for($subscriptionTree)->for($placeholder)->createOne();
        $migratedSubscriptionTree->subscriptions()->attach($subscriptionTree);
        $migratedCustomerB->migratedSubscriptions()->attach($migratedSubscriptionTree);
        $customerB->migratedCustomers()->attach($migratedCustomerB);

        $payload[0]['waterfront_customer_id'] = $customerA->id;
        $payload[1]['waterfront_customer_id'] = $customerB->id;

        $this->actingAsSystem()
            ->postJson(
                $this->generateRoute('ferry.customers.subscriptions.migrate_domain.bulk'),
                $payload,
                [
                    'Authorization' => 'Bearer ferry_testing_api_key',
                    'X-Requested-With' => 'XMLHttpRequest',
                ],
            )
            ->assertStatus(Response::HTTP_MULTI_STATUS)
            ->assertContent('Successfully created bulk technical domain migration jobs');
    }
}
