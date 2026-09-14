<?php

declare(strict_types=1);

namespace Tests\Domain\Subscriptions\Observers;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Subscriptions\Observers\SubscriptionObserver;

#[CoversClass(SubscriptionObserver::class)]
class SubscriptionObserverTest extends IntegrationTestCase
{
    private Product $product;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $productGroup = new ProductGroupFactory()->hosting()->createOne();
        $this->product = new ProductFactory()->for($productGroup)->createOne();
        $this->customer = new CustomerFactory()->createOne();
    }

    #[Test]
    public function populateStartDate(): void
    {
        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($this->product)
            ->createOne();

        self::assertSame($subscription->start_date->toDateString(), CarbonImmutable::now()->toDateString());
    }

    #[Test]
    public function populateNextBillingDate(): void
    {
        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($this->product)
            ->createOne();

        self::assertSame(
            $subscription->next_billing_date->toDateString(),
            $subscription->start_date->addMonths($subscription->billing_period)->toDateString(),
        );
    }

    #[Test]
    public function populateEndDate(): void
    {
        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($this->product)
            ->createOne();

        self::assertSame(
            $subscription->end_date->toDateString(),
            CarbonImmutable::now()->addMonths($subscription->contract_period)->toDateString(),
        );
    }
}
