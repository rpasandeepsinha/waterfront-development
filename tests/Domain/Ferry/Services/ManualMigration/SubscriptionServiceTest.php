<?php

declare(strict_types=1);

namespace Tests\Domain\Ferry\Services\ManualMigration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Tests\DataProvider\DomainSubscriptionDataProvider;
use Tests\Factories\CustomerFactory;
use Tests\Factories\MigratedCustomersFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Ferry\Enum\ImplementableProducts;
use Waterfront\Domain\Ferry\Actions\Subscriptions\StoreSubscriptionAction;
use Waterfront\Domain\Ferry\Exceptions\NoSubscriptionsStoredException;
use Waterfront\Domain\Ferry\Services\ManualMigration\SubscriptionService;

#[CoversClass(SubscriptionService::class)]
class SubscriptionServiceTest extends IntegrationTestCase
{
    #[Test]
    public function storeSubscription(): void
    {
        $customer = new CustomerFactory()->createOne();
        $migrationCustomer = new MigratedCustomersFactory()->createOne();
        $migrationCustomer->customers()->attach($customer);

        $referenceCustomerId = 'some-reference-customer-id';
        $subscriptionData = [
            'subscription' => [
                'domain' => 'testdomain.nl',
                'extension' => 'nl',
                'start_date' => '2021-01-01',
                'next_contract_date' => '2022-01-01',
                'next_billing_date' => '2022-01-01',
                'contract_period' => 1,
                'billing_period' => 1,
                'slug' => 'nl',
                'reference_product_id' => 'some-reference-product-id',
                'reference_subscription_id' => 'some-reference-product-id',
            ],
        ];

        $subscription = DomainSubscriptionDataProvider::subscription();

        $storeSubscriptionAction = self::createStub(StoreSubscriptionAction::class);
        $storeSubscriptionAction->method('execute')
            ->willReturn([$subscription]);

        $service = new SubscriptionService(
            $storeSubscriptionAction,
            self::createStub(LoggerInterface::class),
        );
        $createdSubscription = $service->storeSubscription($customer, $referenceCustomerId, [ImplementableProducts::DOMAIN_EXTENSION->value => $subscriptionData]);

        self::assertSame($createdSubscription->id, $subscription->id);
    }

    #[Test]
    public function storeSubscriptionFailsShouldThrowException(): void
    {
        $customer = new CustomerFactory()->createOne();
        $migrationCustomer = new MigratedCustomersFactory()->createOne();
        $migrationCustomer->customers()->attach($customer);

        $referenceCustomerId = 'some-reference-customer-id';
        $subscriptionData = [
            'subscription' => [
                'domain' => 'testdomain.nl',
                'extension' => 'nl',
                'start_date' => '2021-01-01',
                'next_contract_date' => '2022-01-01',
                'next_billing_date' => '2022-01-01',
                'contract_period' => 1,
                'billing_period' => 1,
                'slug' => 'nl',
                'reference_product_id' => 'some-reference-product-id',
                'reference_subscription_id' => 'some-reference-product-id',
            ],
        ];

        $storeSubscriptionAction = self::createStub(StoreSubscriptionAction::class);
        $storeSubscriptionAction->method('execute')
            ->willReturn([]);

        $service = new SubscriptionService(
            $storeSubscriptionAction,
            self::createStub(LoggerInterface::class),
        );

        $this->expectException(NoSubscriptionsStoredException::class);

        $service->storeSubscription($customer, $referenceCustomerId, [ImplementableProducts::DOMAIN_EXTENSION->value => $subscriptionData]);
    }
}
