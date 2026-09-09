<?php

declare(strict_types=1);

namespace Tests\Domain\Orders\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\OneTimeServiceFactory;
use Tests\Factories\OrderFactory;
use Tests\Factories\OrderLineItemFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Domain\Orders\Enums\OrderStatus;
use Waterfront\Domain\Orders\Exceptions\IncompleteOrderLineException;
use Waterfront\Domain\Orders\Exceptions\OrderAlreadyInvoicedException;
use Waterfront\Domain\Orders\Exceptions\OrderNotProcessedException;
use Waterfront\Domain\Orders\Services\OrderBiller;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;

#[CoversClass(OrderBiller::class)]
class OrderBillerTest extends IntegrationTestCase
{
    #[Test]
    public function orderAlreadyInvoicedWillNotResultInNewInvoices(): void
    {
        self::expectException(OrderAlreadyInvoicedException::class);
        self::assertDatabaseEmpty(Invoice::class);
        $customer = new CustomerFactory()->withAddress()->createOne();
        $order = new OrderFactory()->for($customer)->createOne(['status' => OrderStatus::PROCESSED, 'is_invoiced' => true]);
        new OrderLineItemFactory()->for($order)->createOne();

        $biller = self::resolve(OrderBiller::class);
        $biller->bill($order);
        self::assertTrue($order->is_invoiced);
        self::assertDatabaseEmpty(Invoice::class);
    }

    #[Test]
    public function orderNotProcessedWillNotResultInNewInvoices(): void
    {
        self::expectException(OrderNotProcessedException::class);
        self::assertDatabaseEmpty(Invoice::class);
        $customer = new CustomerFactory()->withAddress()->createOne();
        $order = new OrderFactory()->for($customer)->createOne(['status' => OrderStatus::IN_PROGRESS, 'is_invoiced' => false]);
        new OrderLineItemFactory()->for($order)->createOne();

        $biller = self::resolve(OrderBiller::class);
        $biller->bill($order);
        self::assertFalse($order->is_invoiced);
        self::assertDatabaseEmpty(Invoice::class);
    }

    #[Test]
    public function orderWithoutBillableInvoiceLineItemWillResultInIsInvoiced(): void
    {
        self::assertDatabaseEmpty(Invoice::class);

        $customer = new CustomerFactory()->withAddress()->createOne();
        $order = new OrderFactory()->for($customer)->createOne(['status' => OrderStatus::PROCESSED, 'is_invoiced' => false]);
        $product = new ProductFactory()->for(new ProductGroupFactory()->createOne(['slug' => ProductGroupType::EXTENSION]))->createOne();
        $subscription = new SubscriptionFactory()->for($product)->for($customer)->administrativeStatusActive()->createOne(['administrative_status' => AdministrativeStatus::INACTIVE->value]);
        new OrderLineItemFactory()->for($order)->for($product)->createOne(['subscription_uuid' => $subscription->uuid, 'status' => 'registration']);

        $biller = self::resolve(OrderBiller::class);
        $biller->bill($order);
        self::assertTrue($order->is_invoiced);
        self::assertDatabaseEmpty(Invoice::class);
    }

    #[Test]
    public function orderForWhichOrderLinesAreMissingASubscriptionWillNotResultInNewInvoices(): void
    {
        self::expectException(IncompleteOrderLineException::class);
        self::assertDatabaseEmpty(Invoice::class);
        $customer = new CustomerFactory()->withAddress()->createOne();
        $order = new OrderFactory()->for($customer)->createOne(['status' => OrderStatus::PROCESSED, 'is_invoiced' => false]);
        $product = new ProductFactory()->for(new ProductGroupFactory()->createOne(['slug' => ProductGroupType::EXTENSION]))->createOne();
        new OrderLineItemFactory()->for($order)->for($product)->createOne(['subscription_uuid' => null, 'status' => 'registration']);

        $biller = self::resolve(OrderBiller::class);
        $biller->bill($order);
        self::assertFalse($order->is_invoiced);
        self::assertDatabaseEmpty(Invoice::class);
    }

    #[Test]
    public function orderWillResultInNewInvoices(): void
    {
        $customer = new CustomerFactory()->withAddress()->createOne(['has_direct_debit' => true]);
        $order = new OrderFactory()->for($customer)->createOne(['status' => OrderStatus::PROCESSED, 'is_invoiced' => false, 'administration_fees' => 0]);
        $product = new ProductFactory()->for(new ProductGroupFactory()->extension()->createOne())->createOne();
        $subscription = new SubscriptionFactory()->for($product)->for($customer)->administrativeStatusActive()->createOne(['net_price' => 123]);
        new OrderLineItemFactory()->for($order)->for($product)->createOne(['subscription_uuid' => $subscription->uuid, 'status' => 'registration']);

        $biller = self::resolve(OrderBiller::class);
        $biller->bill($order);
        self::assertTrue($order->is_invoiced);

        $invoices = Invoice::all();
        self::assertCount(1, $invoices);

        $invoice = $invoices->firstOrFail();
        self::assertSame($invoice->subscription_id, $subscription->id);
        self::assertSame($invoice->product->id, $product->id);
    }

    #[Test]
    public function orderWithOTSWillResultInNewInvoices(): void
    {
        $customer = new CustomerFactory()->withAddress()->createOne(['has_direct_debit' => true]);
        $order = new OrderFactory()->for($customer)->createOne(['status' => OrderStatus::PROCESSED, 'is_invoiced' => false, 'administration_fees' => 0]);
        $product = new ProductFactory()->for(new ProductGroupFactory()->oneTimeService()->createOne())->createOne();
        $subscription = new SubscriptionFactory()->for($product)->for($customer)->administrativeStatusActive()->createOne();
        $ots = new OneTimeServiceFactory()->for($customer)->for($subscription)->for($product)->createOne();
        new OrderLineItemFactory()->for($order)->for($product)->createOne([
            'subscription_uuid' => null,
            'one_time_service_id' => $ots->id,
        ]);

        $biller = self::resolve(OrderBiller::class);
        $biller->bill($order);
        self::assertTrue($order->is_invoiced);

        $invoices = Invoice::all();
        self::assertCount(1, $invoices);

        $invoice = $invoices->firstOrFail();
        self::assertSame($invoice->oneTimeServices()->first()?->id, $ots->id);
        self::assertSame($invoice->product->id, $product->id);
    }

    #[Test]
    public function orderWillResultInNewInvoicesIncludingAdminFees(): void
    {
        $customer = new CustomerFactory()->withAddress()->createOne(['has_direct_debit' => false]);
        $order = new OrderFactory()->for($customer)->createOne(['status' => OrderStatus::PROCESSED, 'is_invoiced' => false, 'administration_fees' => 200]);
        $product = new ProductFactory()->for(new ProductGroupFactory()->createOne(['slug' => ProductGroupType::EXTENSION]))->createOne();
        $subscription = new SubscriptionFactory()->for($product)->for($customer)->administrativeStatusActive()->createOne(['net_price' => 123]);
        new OrderLineItemFactory()->for($order)->for($product)->createOne(['subscription_uuid' => $subscription->uuid, 'status' => 'registration']);

        $productAdminFees = ProductFactory::new()->administrationFees()->createOne();
        ProductPriceComponentFactory::new()->administrationFee()->createOne(['product_id' => $productAdminFees->id]);

        $biller = self::resolve(OrderBiller::class);
        $biller->bill($order);
        self::assertTrue($order->is_invoiced);
        self::assertCount(2, Invoice::all());

        self::assertDatabaseHas('invoices', [
            'product_id' => $productAdminFees->id,
            'title' => $productAdminFees->name,
            'gross_price' => 200,
            'net_price' => 200,
        ]);
    }

    #[Test]
    public function orderForComesWithFreeProductWillResultInNewInvoicesIncludingFreeProductInvoice(): void
    {
        $customer = new CustomerFactory()->withAddress()->createOne(['has_direct_debit' => true]);
        $order = new OrderFactory()->for($customer)->createOne(['status' => OrderStatus::PROCESSED, 'is_invoiced' => false, 'administration_fees' => 0]);
        $product = new ProductFactory()->for(new ProductGroupFactory()->hosting()->createOne())->createOne();
        $subscription = new SubscriptionFactory()->for($product)->for($customer)->administrativeStatusActive()->createOne(['net_price' => 123]);
        new OrderLineItemFactory()->for($order)->for($product)->createOne(['subscription_uuid' => $subscription->uuid, 'status' => 'registration']);

        $productGroupAddon = new ProductGroupFactory()->addon()->createOne();
        $productServicePlus = new ProductFactory()->for($productGroupAddon)->createOne();

        new ProductPriceComponentFactory()->for($productServicePlus)->prolongation()->createOne([
            'billing_period' => 12,
            'price' => 100,
        ]);

        $registrationPriceYearly = new ProductPriceComponentFactory()->for($productServicePlus)->registration()->createOne([
            'billing_period' => 12,
            'price' => 90,
        ]);

        $productSpecComesWithFreeProduct = ProductSpecFactory::new()->createOne(
            [
                'name' => ProductSpecName::COMES_WITH_FREE_PRODUCT_SLUG,
                'value' => $productServicePlus->slug,
                'product_id' => $product->id,
            ]
        );

        $product->productSpecs()->save($productSpecComesWithFreeProduct);

        $biller = self::resolve(OrderBiller::class);
        $biller->bill($order);
        self::assertTrue($order->is_invoiced);
        self::assertCount(2, Invoice::all());

        $comesWithFreeProductInvoice = Invoice::query()->where(['product_id' => $productServicePlus->id])->first();
        self::assertInstanceOf(Invoice::class, $comesWithFreeProductInvoice);
        self::assertSame($registrationPriceYearly->price, $comesWithFreeProductInvoice->gross_price);
        self::assertSame(0, $comesWithFreeProductInvoice->net_price);
    }

    #[Test]
    public function orderWithNonInvoicableOrderLineDoesNotResultInInvoice(): void
    {
        $customer = new CustomerFactory()->withAddress()->createOne(['has_direct_debit' => true]);
        $order = new OrderFactory()->for($customer)->createOne(['status' => OrderStatus::PROCESSED, 'is_invoiced' => false, 'administration_fees' => 0]);
        $product = new ProductFactory()->for(new ProductGroupFactory()->createOne())->createOne();
        new OrderLineItemFactory()->for($order)->for($product)->createOne(['should_invoice' => false, 'status' => 'registration']);

        $biller = self::resolve(OrderBiller::class);
        $biller->bill($order);

        self::assertTrue($order->is_invoiced);
        self::assertCount(0, Invoice::all());
    }
}
