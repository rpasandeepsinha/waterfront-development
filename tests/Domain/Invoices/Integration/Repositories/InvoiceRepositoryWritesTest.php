<?php

declare(strict_types=1);

namespace Tests\Domain\Invoices\Integration\Repositories;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use SandwaveIo\HarborMessages\Message\DebtorInvoiceLines\InvoiceLine;
use Tests\Factories\CustomerFactory;
use Tests\Factories\InvoiceFactory;
use Tests\Factories\OrderFactory;
use Tests\Factories\OrderLineItemFactory;
use Tests\Factories\PaymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\DTO\VatDTO;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Services\CustomerVatService;
use Waterfront\Domain\Invoices\Events\InvoiceCreatedEvent;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Domain\Invoices\Repositories\InvoiceRepository;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Subscriptions\DTO\NextInvoicePriceDTO;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Translation\TranslatorInterface;

#[CoversClass(InvoiceRepository::class)]
class InvoiceRepositoryWritesTest extends IntegrationTestCase
{
    private InvoiceRepository $repo;

    private Customer $customer;

    private Subscription $subscription;

    private Product $product;

    public function setUp(): void
    {
        parent::setUp();

        $this->repo = self::resolve(InvoiceRepository::class);

        $this->customer = new CustomerFactory()->createOne();

        $hostingProductGroup = new ProductGroupFactory()->createOne([
            'name' => ProductGroupType::HOSTING,
            'slug' => ProductGroupType::HOSTING,
        ]);

        $this->product = new ProductFactory()->for($hostingProductGroup)->createOne(['name' => 'Test-product']);
        $this->subscription = new SubscriptionFactory()->for($this->customer)->for($this->product)->createOne();
    }

    #[Test]
    public function createWillNotDispatchEvent(): void
    {
        $subscription = new SubscriptionFactory()->for($this->customer)->for($this->product)->createOneQuietly([
            'net_price' => 100,
            'next_billing_date' => CarbonImmutable::now(),
            'end_date' => CarbonImmutable::now()->addYear(),
            'start_date' => CarbonImmutable::now(),
        ]);

        $invoice = Invoice::query()->where('subscription_id', $subscription->id)->first();
        self::assertNull($invoice);

        Event::fake([InvoiceCreatedEvent::class]);

        $this->repo = self::resolve(InvoiceRepository::class);
        $this->repo->create(
            subscription: $subscription,
            dispatchInvoiceCreated: false
        );

        $invoice = Invoice::query()->where('subscription_id', $subscription->id)->first();
        self::assertNotNull($invoice);
        Event::assertNotDispatched(InvoiceCreatedEvent::class);
    }

    #[Test]
    public function createWillDispatchEvent(): void
    {
        $subscription = new SubscriptionFactory()->for($this->customer)->for($this->product)->createOneQuietly([
            'net_price' => 100,
            'next_billing_date' => CarbonImmutable::now(),
            'end_date' => CarbonImmutable::now()->addYear(),
            'start_date' => CarbonImmutable::now(),
        ]);

        $invoice = Invoice::query()->where('subscription_id', $subscription->id)->first();
        self::assertNull($invoice);

        Event::fake([InvoiceCreatedEvent::class]);

        $this->repo = self::resolve(InvoiceRepository::class);
        $this->repo->create($subscription);

        $invoice = Invoice::query()->where('subscription_id', $subscription->id)->first();
        self::assertNotNull($invoice);
        Event::assertDispatched(InvoiceCreatedEvent::class);
    }

    #[Test]
    public function createInvoice(): void
    {
        $subscription = new SubscriptionFactory()->for($this->customer)->for($this->product)->createOneQuietly([
            'net_price' => 100,
            'next_billing_date' => CarbonImmutable::now()->addMonth(),
            'end_date' => CarbonImmutable::now()->addYear(),
            'start_date' => CarbonImmutable::now(),
            'contract_period' => 24,
            'billing_period' => 12,
        ]);

        $this->repo->createInvoice($subscription);

        $invoice = Invoice::query()->where('subscription_id', $subscription->id)->firstOrFail();
        self::assertSame($invoice->subscription_id, $subscription->id);
        self::assertSame($invoice->customer_id, $this->customer->id);
        self::assertSame((string) $invoice->ledger_code, (string) $subscription->product->productGroup->ledger_code);
        self::assertFalse($invoice->paid);
        self::assertSame($invoice->product_id, $subscription->product->id);
        self::assertSame($invoice->start_date->toDateString(), $subscription->start_date->toDateString());
        self::assertSame($invoice->end_date->toDateString(), $subscription->next_billing_date->toDateString());
        self::assertSame($invoice->period, $subscription->billing_period);
        self::assertSame($invoice->gross_price, $subscription->gross_price);
        self::assertSame($invoice->net_price, $subscription->net_price);
        self::assertSame($subscription->domain, $invoice->title);
        self::assertSame($subscription->product->name . ' invoice.description.for ' . $subscription->domain, $invoice->description);
        self::assertSame($subscription->domain, $invoice->group_label);
        self::assertSame(InvoiceLine::TYPE_DEFAULT, $invoice->type);
        self::assertNull($invoice->prepaid_reference);
    }

    #[Test]
    public function createInvoiceWithPrepaidReference(): void
    {
        $subscription = new SubscriptionFactory()->for($this->customer)->for($this->product)->createOneQuietly([
            'net_price' => 100,
            'next_billing_date' => CarbonImmutable::now()->addMonth(),
            'end_date' => CarbonImmutable::now()->addYear(),
            'start_date' => CarbonImmutable::now(),
            'contract_period' => 24,
            'billing_period' => 12,
        ]);
        $order = new OrderFactory()->for($this->customer)->createOne();
        $payment = new PaymentFactory()
            ->for($this->customer)
            ->for($order)
            ->createOne();
        new OrderLineItemFactory()
            ->for($subscription)
            ->for($order)
            ->createOne();

        $this->repo->createInvoice(subscription: $subscription, paid: true);

        $invoice = Invoice::query()->where('subscription_id', $subscription->id)->firstOrFail();
        self::assertSame($invoice->subscription_id, $subscription->id);
        self::assertSame($invoice->customer_id, $this->customer->id);
        self::assertSame((string) $invoice->ledger_code, (string) $subscription->product->productGroup->ledger_code);
        self::assertTrue($invoice->paid);
        self::assertSame($invoice->product_id, $subscription->product->id);
        self::assertSame($invoice->start_date->toDateString(), $subscription->start_date->toDateString());
        self::assertSame($invoice->end_date->toDateString(), $subscription->next_billing_date->toDateString());
        self::assertSame($invoice->period, $subscription->billing_period);
        self::assertSame($invoice->gross_price, $subscription->gross_price);
        self::assertSame($invoice->net_price, $subscription->net_price);
        self::assertSame($subscription->domain, $invoice->title);
        self::assertSame($subscription->product->name . ' invoice.description.for ' . $subscription->domain, $invoice->description);
        self::assertSame($subscription->domain, $invoice->group_label);
        self::assertSame(InvoiceLine::TYPE_DEFAULT, $invoice->type);
        self::assertSame($payment->external_id, $invoice->prepaid_reference);
    }

    #[Test]
    public function createInvoiceOverrulesStartDate(): void
    {
        $subscription = new SubscriptionFactory()->for($this->customer)->for($this->product)->createOneQuietly([
            'net_price' => 100,
            'next_billing_date' => CarbonImmutable::now(),
            'end_date' => CarbonImmutable::now()->addYear(),
            'start_date' => CarbonImmutable::now(),
        ]);

        $startDate = CarbonImmutable::now()->addYear();

        $this->repo->createInvoice($subscription, $startDate);

        $invoice = Invoice::query()->where('subscription_id', $subscription->id)->firstOrFail();
        self::assertSame($invoice->start_date->toDateString(), $startDate->toDateString());
    }

    #[Test]
    public function createInvoiceOverrulesPaid(): void
    {
        $subscription = new SubscriptionFactory()->for($this->customer)->for($this->product)->createOneQuietly([
            'net_price' => 100,
            'next_billing_date' => CarbonImmutable::now(),
            'end_date' => CarbonImmutable::now()->addYear(),
            'start_date' => CarbonImmutable::now(),
        ]);

        $this->repo->createInvoice($subscription, null, true);

        $invoice = Invoice::query()->where('subscription_id', $subscription->id)->firstOrFail();
        self::assertTrue($invoice->paid);
    }

    #[Test]
    public function createInvoiceUsesEndDateForLastInvoice(): void
    {
        $nextBillingDate = CarbonImmutable::now();
        $subscriptionEndDate = CarbonImmutable::now();

        $subscription = new SubscriptionFactory()->for($this->customer)->for($this->product)->createOneQuietly([
            'net_price' => 100,
            'next_billing_date' => $nextBillingDate,
            'end_date' => $subscriptionEndDate,
            'start_date' => CarbonImmutable::now()->subYear(),
        ]);

        $this->repo->createInvoice($subscription, $subscription->next_billing_date, true);

        $invoice = Invoice::query()->where('subscription_id', $subscription->id)->firstOrFail();
        self::assertSame($invoice->end_date->toDateString(), $subscription->end_date->toDateString());
    }

    #[Test]
    public function lockInvoiceLine(): void
    {
        $invoice = new InvoiceFactory()
            ->for($this->customer)
            ->for($this->product)
            ->for($this->subscription)
            ->createOne([
                'paid' => true,
                'sent_to_harbor_at' => null,
            ]);

        $updated = $this->repo->lockInvoiceLine($invoice->id);
        $updatedInvoice = Invoice::where('id', $invoice->id)->firstOrFail();

        self::assertSame(1, $updated);

        self::assertEquals($updatedInvoice->sent_to_harbor_at, CarbonImmutable::create(1999));
    }

    #[Test]
    public function createNextSubscriptionInvoice(): void
    {
        $subscription = new SubscriptionFactory()->for($this->customer)->for($this->product)->createOne([
            'billing_period' => 12,
            'contract_period' => 12,
            'net_price' => 100,
            'next_billing_date' => CarbonImmutable::now(),
            'end_date' => CarbonImmutable::now()->addYear(),
            'start_date' => CarbonImmutable::now(),
            'domain' => 'next-subscription.test',
        ]);

        $vatService = self::createMock(CustomerVatService::class);
        $vatService->expects(self::once())
            ->method('getCustomerVatData')
            ->willReturn(
                new VatDTO(
                    vatCode: 'TEST1',
                    vatRate: 99,
                )
            );

        $eventDispatcher = self::createMock(Dispatcher::class);
        $eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(InvoiceCreatedEvent::class));

        $nextInvoicePrice = new NextInvoicePriceDTO(
            $this->product,
            24,
            200,
            190,
            null,
            CarbonImmutable::today(),
            CarbonImmutable::now()->addYears(2)
        );

        $repo = new InvoiceRepository(
            $vatService,
            $eventDispatcher,
            self::resolve(TranslatorInterface::class)
        );

        $invoice = $repo->createNextSubscriptionInvoice(
            $subscription,
            $nextInvoicePrice
        );

        self::assertSame($this->customer->id, $invoice->customer_id);
        self::assertSame(99.0, $invoice->vat_rate);
        self::assertSame('TEST1', $invoice->vat_code);
        self::assertSame($this->product->id, $invoice->product_id);
        self::assertSame($subscription->id, $invoice->subscription_id);
        self::assertSame($subscription->domain, $invoice->group_label);
        self::assertSame($this->product->productGroup->ledger_code, $invoice->ledger_code);
        self::assertFalse($invoice->paid);
        self::assertSame($nextInvoicePrice->startDate->toDateString(), $invoice->start_date->toDateString());
        self::assertSame($nextInvoicePrice->endDate->toDateString(), $invoice->end_date->toDateString());
        self::assertSame($nextInvoicePrice->billingPeriod, $invoice->period);
        self::assertSame($nextInvoicePrice->grossPrice, $invoice->gross_price);
        self::assertSame($nextInvoicePrice->netPrice, $invoice->net_price);
        self::assertSame($subscription->domain, $invoice->title);
        self::assertSame($subscription->product->name . ' invoice.description.for ' . $subscription->domain, $invoice->description);
    }

    #[Test]
    public function createNextSubscriptionInvoiceWithoutDispatching(): void
    {
        $subscription = new SubscriptionFactory()->for($this->customer)->for($this->product)->createOne();

        $vatService = self::createStub(CustomerVatService::class);
        $vatService->method('getCustomerVatData')
            ->willReturn(
                new VatDTO(
                    vatCode: 'TEST2',
                    vatRate: 1,
                )
            );

        $eventDispatcher = self::createMock(Dispatcher::class);
        $eventDispatcher->expects(self::never())
            ->method('dispatch')
            ->with(self::isInstanceOf(InvoiceCreatedEvent::class));

        $nextInvoicePrice = new NextInvoicePriceDTO(
            $this->product,
            12,
            10,
            10,
            null,
            CarbonImmutable::today(),
            CarbonImmutable::now()->addYears(1)
        );

        $repo = new InvoiceRepository(
            $vatService,
            $eventDispatcher,
            self::resolve(TranslatorInterface::class)
        );

        $invoice = $repo->createNextSubscriptionInvoice($subscription, $nextInvoicePrice, false);

        self::assertTrue($invoice->exists);
    }

    #[Test]
    public function createOrderLineSubscriptionInvoice(): void
    {
        $order = new OrderFactory()->for($this->customer)->createOne();

        $product = $this->product = new ProductFactory()
            ->for(new ProductGroupFactory()->dns()->createOne())
            ->createOne(['name' => 'ordered-product']);

        $orderLine = new OrderLineItemFactory()
            ->for($order)
            ->for($product)
            ->for($this->subscription)
            ->createOne([
                'billing_period' => 1,
                'contract_period' => 1,
                'gross_price' => 500,
                'net_price' => 400,
                'domain' => 'order-line-subscription.test',
            ]);

        $vatService = self::createMock(CustomerVatService::class);
        $vatService->expects(self::once())
            ->method('getCustomerVatData')
            ->willReturn(
                new VatDTO(
                    vatCode: 'TEST8',
                    vatRate: 50,
                )
            );

        $eventDispatcher = self::createMock(Dispatcher::class);
        $eventDispatcher->expects(self::never())
            ->method('dispatch')
            ->with(self::isInstanceOf(InvoiceCreatedEvent::class));

        $repo = new InvoiceRepository(
            $vatService,
            $eventDispatcher,
            self::resolve(TranslatorInterface::class)
        );

        $invoice = $repo->createOrderLineSubscriptionInvoice(
            $this->customer,
            $orderLine,
            'tr_order_mollie_ref'
        );

        self::assertSame($this->customer->id, $invoice->customer_id);
        self::assertSame(50.0, $invoice->vat_rate);
        self::assertSame('TEST8', $invoice->vat_code);
        self::assertSame($product->id, $invoice->product_id);
        self::assertSame($this->subscription->id, $invoice->subscription_id);
        self::assertSame($orderLine->domain, $invoice->group_label);
        self::assertSame($this->product->productGroup->ledger_code, $invoice->ledger_code);
        self::assertTrue($invoice->paid);
        self::assertSame('tr_order_mollie_ref', $invoice->prepaid_reference);
        self::assertSame($this->subscription->start_date->toDateString(), $invoice->start_date->toDateString());
        self::assertSame($this->subscription->next_billing_date->toDateString(), $invoice->end_date->toDateString());
        self::assertSame($orderLine->billing_period, $invoice->period);
        self::assertSame($orderLine->gross_price, $invoice->gross_price);
        self::assertSame($orderLine->net_price, $invoice->net_price);
        self::assertSame($orderLine->domain, $invoice->title);
        self::assertSame($product->name . ' invoice.description.for ' . $orderLine->domain, $invoice->description);
    }
}
