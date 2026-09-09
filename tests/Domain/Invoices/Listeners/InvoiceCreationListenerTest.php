<?php

declare(strict_types=1);

namespace Tests\Domain\Invoices\Listeners;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\InvoiceFactory;
use Tests\Factories\MigratedCustomersFactory;
use Tests\Factories\MigratedSubscriptionsFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Harbor\Listeners\InvoiceCreatedListener;
use Waterfront\Domain\Invoices\Events\InvoiceCreatedEvent;

#[CoversClass(InvoiceCreatedListener::class)]
class InvoiceCreationListenerTest extends IntegrationTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        Model::preventLazyLoading(false);
    }

    #[Test]
    public function invoiceCreatedListener(): void
    {
        Queue::fake();

        $customer = new CustomerFactory()->createOne();
        $productGroup = new ProductGroupFactory()->hosting()->createOne();
        $product  = new ProductFactory()->for($productGroup)->createOne();
        $subscription = new SubscriptionFactory()->withCustomer()->createOne([
            'customer_id' => $customer->id,
            'product_uuid' => $product->uuid,
        ]);

        $invoice = new InvoiceFactory()
            ->for($customer)
            ->for($subscription)
            ->for($product)
            ->createOne([
                'start_date' => CarbonImmutable::now(),
                'end_date' => CarbonImmutable::now()->addYear(),
            ]);

        self::resolve(Dispatcher::class)->dispatch(new InvoiceCreatedEvent($invoice, false));

        Queue::assertPushed(CallQueuedListener::class, function (CallQueuedListener $job): bool {
            $event = reset($job->data);
            self::assertInstanceOf(InvoiceCreatedEvent::class, $event);
            self::assertTrue($job->afterCommit);

            return $event->invoice->subscription !== null;
        });
    }

    #[Test]
    public function invoiceCreatedDelayedForDisabledInvoicingOnMigratedCustomer(): void
    {
        $this->expectNotToPerformAssertions();

        $customer = new CustomerFactory()->createOne();
        new MigratedCustomersFactory()->createOne([
            'successful' => true,
            'enable_invoicing' => true,
        ])->customers()->attach($customer);

        $migCustomer = new MigratedCustomersFactory()->createOne([
            'successful' => true,
            'enable_invoicing' => false,
        ]);
        $migCustomer->customers()->attach($customer);

        $migSubscription = new MigratedSubscriptionsFactory()->createOne();
        $productGroup = new ProductGroupFactory()->hosting()->createOne();
        $product  = new ProductFactory()->for($productGroup)->createOne();

        $subscription = new SubscriptionFactory()->withCustomer()->createOne([
            'customer_id' => $customer->id,
            'product_uuid' => $product->uuid,
        ]);

        $migSubscription->subscriptions()->attach($subscription);
        $migCustomer->migratedSubscriptions()->attach($migSubscription);
        $migSubscription->save();
        $migCustomer->save();

        $invoice = new InvoiceFactory()
            ->for($customer)
            ->for($subscription)
            ->for($product)
            ->createOne([
                'start_date' => CarbonImmutable::now(),
                'end_date' => CarbonImmutable::now()->addYear(),
            ]);

        self::resolve(Dispatcher::class)->dispatch(new InvoiceCreatedEvent($invoice, false));
    }
}
