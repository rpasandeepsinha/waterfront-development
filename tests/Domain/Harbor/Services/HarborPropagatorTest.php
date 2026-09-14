<?php

declare(strict_types=1);

namespace Tests\Domain\Harbor\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerAddressFactory;
use Tests\Factories\CustomerFactory;
use Tests\Factories\InvoiceFactory;
use Tests\Factories\OrderFactory;
use Tests\Factories\OrderLineItemFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Harbor\Services\HarborPropagator;
use Waterfront\Domain\Products\Models\Product;

#[CoversClass(HarborPropagator::class)]
class HarborPropagatorTest extends IntegrationTestCase
{
    private Customer $customer;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        Model::preventLazyLoading(false);

        $this->customer = new CustomerFactory()->createOne([
            'locale' => 'nl-NL',
        ]);
        new CustomerAddressFactory()->create([
            'customer_id' => $this->customer->id,
        ]);
        $group = new ProductGroupFactory()->hosting()->createOne();
        $this->product = new ProductFactory()->createOne([
            'product_group_id' => $group->id,
        ]);
    }

    #[Test]
    public function propagate(): void
    {
        $this->expectNotToPerformAssertions();

        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($this->product)
            ->technicalStatusDomainActive()
            ->createOne();

        $subscription2 = new SubscriptionFactory()
            ->for($this->customer)
            ->for($this->product)
            ->technicalStatusDomainActive()
            ->createOne();

        $invoice = new InvoiceFactory()
            ->for($this->customer)
            ->for($subscription)
            ->for($this->product)
            ->createOne([
                'start_date' => CarbonImmutable::now(),
                'end_date' => CarbonImmutable::now()->addYear(),
            ]);

        $invoice2 = new InvoiceFactory()
            ->for($this->customer)
            ->for($subscription2)
            ->for($this->product)
            ->createOne([
                'start_date' => CarbonImmutable::now(),
                'end_date' => CarbonImmutable::now()->addYear(),
            ]);

        $order = new OrderFactory()->for($this->customer)->createOne();

        $orderLineItem1 = new OrderLineItemFactory()->make([
            'subscription_uuid' => $subscription->uuid,
            'product_uuid' => $this->product->uuid,
        ]);

        $orderLineItem2 = new OrderLineItemFactory()->make([
            'subscription_uuid' => $subscription2->uuid,
            'product_uuid' => $this->product->uuid,
        ]);

        $order->lineItems()->saveMany([$orderLineItem1, $orderLineItem2]);

        $propagator = self::resolve(HarborPropagator::class);

        $propagator->propagate($invoice);
        $propagator->propagate($invoice2);
        $propagator->propagate($invoice2);
    }

    #[Test]
    public function propagateStandAloneSubscriptions(): void
    {
        $this->expectNotToPerformAssertions();

        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($this->product)
            ->technicalStatusDomainActive()
            ->createOne();

        $subscription2 = new SubscriptionFactory()
            ->for($this->customer)
            ->for($this->product)
            ->technicalStatusDomainActive()
            ->createOne();

        $invoice = new InvoiceFactory()
            ->for($this->customer)
            ->for($subscription)
            ->for($this->product)
            ->createOne([
                'start_date' => CarbonImmutable::now(),
                'end_date' => CarbonImmutable::now()->addYear(),
            ]);

        $invoice2 = new InvoiceFactory()
            ->for($this->customer)
            ->for($subscription2)
            ->for($this->product)
            ->createOne([
                'start_date' => CarbonImmutable::now(),
                'end_date' => CarbonImmutable::now()->addYear(),
            ]);

        $propagator = self::resolve(HarborPropagator::class);

        $propagator->propagate($invoice);
        $propagator->propagate($invoice2);
    }

    #[Test]
    public function propagateInvalidOrderLineItem(): void
    {
        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($this->product)
            ->technicalStatusDomainActive()
            ->createOne();

        $subscription2 = new SubscriptionFactory()
            ->for($this->customer)
            ->for($this->product)
            ->technicalStatusDomainActive()
            ->createOne();

        $invoice = new InvoiceFactory()
            ->for($this->customer)
            ->for($subscription)
            ->for($this->product)
            ->createOne([
                'start_date' => CarbonImmutable::now(),
                'end_date' => CarbonImmutable::now()->addYear(),
            ]);

        $invoice2 = new InvoiceFactory()
            ->for($this->customer)
            ->for($subscription2)
            ->for($this->product)
            ->createOne([
                'start_date' => CarbonImmutable::now(),
                'end_date' => CarbonImmutable::now()->addYear(),
            ]);

        $order = new OrderFactory()->for($this->customer)->createOne();

        $orderLineItem1 = new OrderLineItemFactory()->make([
            'subscription_uuid' => $subscription->uuid,
            'product_uuid' => $this->product->uuid,
        ]);

        $orderLineItem2 = new OrderLineItemFactory()->make([
            'subscription_uuid' => null,
            'product_uuid' => $this->product->uuid,
        ]);

        $order->lineItems()->saveMany([$orderLineItem1, $orderLineItem2]);

        $propagator = self::resolve(HarborPropagator::class);

        Log::shouldReceive('error')->once();

        $propagator->propagate($invoice);
        $propagator->propagate($invoice2);
    }
}
