<?php

declare(strict_types=1);

namespace Tests\Domain\Harbor\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerAddressFactory;
use Tests\Factories\CustomerFactory;
use Tests\Factories\InvoiceFactory;
use Tests\Factories\MigratedCustomersFactory;
use Tests\Factories\MigratedSubscriptionsFactory;
use Tests\Factories\OrderFactory;
use Tests\Factories\OrderLineItemFactory;
use Tests\Factories\PaymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Harbor\Services\HarborPropagationArbiter;
use Waterfront\Domain\Payments\Enums\PaymentStatus;
use Waterfront\Domain\Products\Models\Product;

#[CoversClass(HarborPropagationArbiter::class)]
class HarborPropagationArbiterTest extends IntegrationTestCase
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
        $productGroup = new ProductGroupFactory()->hosting()->createOne();
        $this->product = new ProductFactory()->for($productGroup)->createOne();
    }

    #[Test]
    public function withRegularFlow(): void
    {
        new MigratedCustomersFactory()
            ->createOne([
                'successful' => true,
                'enable_invoicing' => true,
            ])
            ->customers()
            ->attach($this->customer);

        $subscriptionData = [
            'product_uuid' => $this->product->uuid,
            'technical_status' => DomainStatus::ACTIVE->value,
        ];

        $subscription = new SubscriptionFactory()->for($this->customer)->createOne($subscriptionData);
        $subscription2 = new SubscriptionFactory()->for($this->customer)->createOne($subscriptionData);

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
        $order->customer()->associate($this->customer);

        $arbiter = self::resolve(HarborPropagationArbiter::class);

        $result1 = $arbiter->allowedToPropagate($invoice);
        $result2 = $arbiter->allowedToPropagate($invoice2);

        self::assertTrue($result1->isPropagationAllowed());
        self::assertNull($result1->getReason());

        self::assertTrue($result2->isPropagationAllowed());
    }

    #[Test]
    public function withPrepaid(): void
    {
        $customer = new CustomerFactory()->createOne([
            'locale' => 'nl-NL',
        ]);
        new CustomerAddressFactory()->create([
            'customer_id' => $customer->id,
        ]);

        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($this->product)
            ->technicalStatus(DomainStatus::FAILED->value)
            ->createOne();

        $invoice = new InvoiceFactory()
            ->for($customer)
            ->for($subscription)
            ->for($this->product)
            ->createOne([
                'start_date' => CarbonImmutable::now(),
                'end_date' => CarbonImmutable::now()->addYear(),
                'paid' => true,
            ]);

        $order = new OrderFactory()->for($customer)->createOne();

        $orderLineItem1 = new OrderLineItemFactory()->makeOne([
            'subscription_uuid' => $subscription->uuid,
            'product_uuid' => $this->product->uuid,
        ]);

        $payment = new PaymentFactory()->createOne([
            'status' => PaymentStatus::PAID,
            'customer_uuid' => $customer->uuid,
        ]);

        $order->payments()->save($payment);
        $order->lineItems()->save($orderLineItem1);
        $order->customer()->associate($this->customer);
        $order->customer()->associate($customer);

        $arbiter = self::resolve(HarborPropagationArbiter::class);

        $result1 = $arbiter->allowedToPropagate($invoice);

        self::assertTrue($result1->isPropagationAllowed());
        self::assertSame('Invoice is prepaid!', $result1->getReason());
    }

    #[Test]
    public function withRenew(): void
    {
        $subscriptionData = [
            'product_uuid' => $this->product->uuid,
            'technical_status' => DomainStatus::ACTIVE->value,
        ];
        $subscription = new SubscriptionFactory()->for($this->customer)->createOne($subscriptionData);
        $subscription2 = new SubscriptionFactory()->for($this->customer)->createOne($subscriptionData);

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
            ->for($subscription)
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
        $order->customer()->associate($this->customer);

        $arbiter = self::resolve(HarborPropagationArbiter::class);

        $result1 = $arbiter->allowedToPropagate($invoice);
        $result2 = $arbiter->allowedToPropagate($invoice2);

        self::assertTrue($result1->isPropagationAllowed());
        self::assertTrue($result2->isPropagationAllowed());
    }

    #[Test]
    public function withRenewBadTechnical(): void
    {
        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($this->product)
            ->technicalStatus(DomainStatus::FAILED->value)
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
            ->for($subscription)
            ->for($this->product)
            ->createOne([
                'start_date' => CarbonImmutable::now(),
                'end_date' => CarbonImmutable::now()->addYear(),
            ]);

        $order = new OrderFactory()->for($this->customer)->createOne();
        $payment = new PaymentFactory()->createOne([
            'status' => PaymentStatus::PAID,
            'customer_uuid' => $subscription->customer->uuid,
        ]);

        $order->payments()->save($payment);

        $orderLineItem1 = new OrderLineItemFactory()->make([
            'subscription_uuid' => $subscription->uuid,
            'product_uuid' => $this->product->uuid,
        ]);

        $orderLineItem2 = new OrderLineItemFactory()->make([
            'subscription_uuid' => $subscription2->uuid,
            'product_uuid' => $this->product->uuid,
        ]);

        $order->lineItems()->saveMany([$orderLineItem1, $orderLineItem2]);
        $order->customer()->associate($this->customer);

        $arbiter = self::resolve(HarborPropagationArbiter::class);

        $result1 = $arbiter->allowedToPropagate($invoice);
        $result2 = $arbiter->allowedToPropagate($invoice2);

        self::assertTrue($result1->isPropagationAllowed());
        self::assertTrue($result2->isPropagationAllowed());
    }

    #[Test]
    public function withRenewal(): void
    {
        $subscriptionData = [
            'product_uuid' => $this->product->uuid,
            'technical_status' => DomainStatus::ACTIVE->value,
        ];
        $subscription = new SubscriptionFactory()->for($this->customer)->createOne($subscriptionData);
        $subscription2 = new SubscriptionFactory()->for($this->customer)->createOne($subscriptionData);

        $invoice = new InvoiceFactory()
            ->for($this->customer)
            ->for($subscription)
            ->for($this->product)
            ->createOne([
                'start_date' => CarbonImmutable::now(),
                'created_at' => CarbonImmutable::now(),
                'end_date' => CarbonImmutable::now()->addYear(),
            ]);

        new InvoiceFactory()
            ->for($this->customer)
            ->for($subscription)
            ->for($this->product)
            ->createOne([
                'start_date' => CarbonImmutable::now()->subYear(),
                'end_date' => CarbonImmutable::now(),
                'created_at' => CarbonImmutable::now()->subYear(),
                'sent_to_harbor_at' => CarbonImmutable::now()->subYear(),
            ]);

        new InvoiceFactory()
            ->for($this->customer)
            ->for($subscription)
            ->for($this->product)
            ->createOne([
                'start_date' => CarbonImmutable::now()->subYears(2),
                'created_at' => CarbonImmutable::now()->subYears(2),
                'end_date' => CarbonImmutable::now()->subYears(1),
                'sent_to_harbor_at' => CarbonImmutable::now()->subYears(2),
            ]);

        $invoice2 = new InvoiceFactory()
            ->for($this->customer)
            ->for($subscription)
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
        $order->customer()->associate($this->customer);

        $arbiter = self::resolve(HarborPropagationArbiter::class);

        $result1 = $arbiter->allowedToPropagate($invoice);
        $result2 = $arbiter->allowedToPropagate($invoice2);

        self::assertTrue($result1->isPropagationAllowed());
        self::assertTrue($result2->isPropagationAllowed());
    }

    #[Test]
    public function withTechnicals(): void
    {
        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($this->product)
            ->technicalStatus(DomainStatus::FAILED->value)
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
            ->for($subscription)
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
        $order->customer()->associate($this->customer);

        $arbiter = self::resolve(HarborPropagationArbiter::class);

        $result1 = $arbiter->allowedToPropagate($invoice);
        $result2 = $arbiter->allowedToPropagate($invoice2);

        self::assertTrue($result1->isPropagationAllowed());
        self::assertTrue($result2->isPropagationAllowed());
    }

    #[Test]
    public function withNonExistingSubscription(): void
    {
        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($this->product)
            ->technicalStatus(DomainStatus::FAILED->value)
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
            ->for($subscription)
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

        $orderLineItem3 = new OrderLineItemFactory()->make([
            'subscription_uuid' => null,
            'product_uuid' => $this->product->uuid,
        ]);

        $order->lineItems()->saveMany([$orderLineItem1, $orderLineItem2, $orderLineItem3]);
        $order->customer()->associate($this->customer);
        $order->save();

        $arbiter = self::resolve(HarborPropagationArbiter::class);

        $result1 = $arbiter->allowedToPropagate($invoice);
        $result2 = $arbiter->allowedToPropagate($invoice2);

        self::assertTrue($result1->isPropagationAllowed());
        self::assertTrue($result2->isPropagationAllowed());
    }

    #[Test]
    public function withAnonymizingCustomer(): void
    {
        $this->customer->update([
            'anonymized_at' => CarbonImmutable::now(),
            'first_name' => "anonymized-first_name-{$this->customer->customer_number}",
            'last_name' => "anonymized-last_name-{$this->customer->customer_number}",
            'email' => "anonymized.customer.{$this->customer->customer_number}@sandwave.io",
            'organization' => 'anonymized-organisation',
            'department' => 'anonymized-department',
            'phone_country_code' => '(+31)',
            'phone_area_code' => '6',
            'phone_subscriber_number' => '12345678',
            'coc_number' => '1234567890',
            'vat_number' => '1234567890',
        ]);

        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($this->product)
            ->technicalStatus(DomainStatus::FAILED->value)
            ->createOne();

        $invoice = new InvoiceFactory()
            ->for($this->customer)
            ->for($subscription)
            ->for($this->product)
            ->createOne([
                'start_date' => CarbonImmutable::now(),
                'end_date' => CarbonImmutable::now()->addYear(),
            ]);

        $order = new OrderFactory()->for($this->customer)->createOne();

        $orderLineItem = new OrderLineItemFactory()->makeOne([
            'subscription_uuid' => $subscription->uuid,
            'product_uuid' => $this->product->uuid,
        ]);

        $order->lineItems()->save($orderLineItem);
        $order->customer()->associate($this->customer);
        $order->save();

        $arbiter = self::resolve(HarborPropagationArbiter::class);

        $result = $arbiter->allowedToPropagate($invoice);

        self::assertFalse($result->isPropagationAllowed());
        self::assertStringContainsString('for an anonymized customer', strval($result->getReason()));
    }

    #[Test]
    public function withIncompleteMigrationCustomerAndMigrationSubscription(): void
    {
        $migCustomer = new MigratedCustomersFactory()->createOne([
            'successful' => true,
            'enable_invoicing' => false,
        ]);

        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($this->product)
            ->technicalStatusDomainActive()
            ->createOne();

        $migSubscription = new MigratedSubscriptionsFactory()->createOne();

        $migSubscription->subscriptions()->attach($subscription);
        $migCustomer->customers()->attach($this->customer);
        $migCustomer->migratedSubscriptions()->attach($migSubscription);

        $migSubscription->save();
        $migCustomer->save();

        $invoice = new InvoiceFactory()
            ->for($this->customer)
            ->for($subscription)
            ->for($this->product)
            ->createOne([
                'start_date' => CarbonImmutable::now(),
                'end_date' => CarbonImmutable::now()->addYear(),
            ]);

        $arbiter = self::resolve(HarborPropagationArbiter::class);
        $result = $arbiter->allowedToPropagate($invoice);

        self::assertFalse($result->isPropagationAllowed());
        self::assertStringContainsString('Invoicing is disabled for customer', strval($result->getReason()));
    }

    #[Test]
    public function withIncompleteMigrationCustomerAndNonMigrationSubscription(): void
    {
        $migCustomer = new MigratedCustomersFactory()->createOne([
            'successful' => true,
            'enable_invoicing' => false,
        ]);

        $subscription = new SubscriptionFactory()->for($this->customer)->createOne([
            'product_uuid' => $this->product->uuid,
            'technical_status' => DomainStatus::ACTIVE->value,
        ]);

        $migCustomer->customers()->attach($this->customer);
        $migCustomer->save();

        $invoice = new InvoiceFactory()
            ->for($this->customer)
            ->for($subscription)
            ->for($this->product)
            ->createOne([
                'start_date' => CarbonImmutable::now(),
                'end_date' => CarbonImmutable::now()->addYear(),
            ]);

        $arbiter = self::resolve(HarborPropagationArbiter::class);
        $result = $arbiter->allowedToPropagate($invoice);

        self::assertFalse($result->isPropagationAllowed());
        self::assertStringContainsString('Invoicing is disabled for customer', strval($result->getReason()));
    }
}
