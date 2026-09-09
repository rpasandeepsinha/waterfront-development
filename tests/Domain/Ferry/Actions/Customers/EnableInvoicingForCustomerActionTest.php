<?php

declare(strict_types=1);

namespace Tests\Domain\Ferry\Actions\Customers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\InvoiceFactory;
use Tests\Factories\MigratedCustomersFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Ferry\Actions\Customers\EnableInvoicingForCustomerAction;
use Waterfront\Domain\Invoices\Events\InvoiceCreatedEvent;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;

#[CoversClass(EnableInvoicingForCustomerAction::class)]
class EnableInvoicingForCustomerActionTest extends IntegrationTestCase
{
    #[Test]
    public function execute(): void
    {
        $customer = CustomerFactory::new()->createOne();
        $migratedCustomer = MigratedCustomersFactory::new()->createOne(['enable_invoicing' => false]);
        $migratedCustomer->customers()->attach($customer->id);

        // create a product group, where the product can reside in
        $productGroup = ProductGroupFactory::new()->microsoft365()->createOne();

        // create some product
        $product = ProductFactory::new()->for($productGroup)->createOne();

        // attach a price to given product
        ProductPriceComponentFactory::new()->for($product)->registration()->createOne();

        // the parent subscription
        $subscription = SubscriptionFactory::new()->withCustomer()->for($product)->createOne([
            'administrative_status' => AdministrativeStatus::ACTIVE->value,
        ]);

        InvoiceFactory::new()
            ->for($customer)
            ->for($subscription)
            ->for($product)
            ->createOne([
                'start_date' => CarbonImmutable::now(),
                'end_date' => CarbonImmutable::now()->addYear(),
                'sent_to_harbor_at' => null,
            ]);

        InvoiceFactory::new()
            ->for($customer)
            ->for($subscription)
            ->for($product)
            ->createOne([
                'start_date' => CarbonImmutable::now(),
                'end_date' => CarbonImmutable::now()->addYear(),
                'sent_to_harbor_at' => null,
            ]);

        InvoiceFactory::new()
            ->for($customer)
            ->for($subscription)
            ->for($product)
            ->createOne([
                'start_date' => CarbonImmutable::now(),
                'end_date' => CarbonImmutable::now()->addYear(),
                'sent_to_harbor_at' => CarbonImmutable::now(),
            ]);

        Event::fake();

        $enableInvoicingForCustomerAction = self::resolve(EnableInvoicingForCustomerAction::class);
        $enableInvoicingForCustomerAction->execute($customer);

        $savedMigratedCustomer = $customer->migratedCustomers->firstOrFail();
        self::assertTrue($savedMigratedCustomer->enable_invoicing);

        Event::assertDispatched(InvoiceCreatedEvent::class);
        Event::assertDispatchedTimes(InvoiceCreatedEvent::class, 2);
    }
}
