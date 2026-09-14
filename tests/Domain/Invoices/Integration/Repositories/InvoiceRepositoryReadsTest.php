<?php

declare(strict_types=1);

namespace Tests\Domain\Invoices\Integration\Repositories;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\InvoiceFactory;
use Tests\Factories\OrderFactory;
use Tests\Factories\OrderLineItemFactory;
use Tests\Factories\PaymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Invoices\Repositories\InvoiceRepository;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\ProductChangeType;
use Waterfront\Infra\Translation\TranslatorInterface;

#[CoversClass(InvoiceRepository::class)]
class InvoiceRepositoryReadsTest extends IntegrationTestCase
{
    private InvoiceRepository $invoiceRepository;

    public function setUp(): void
    {
        parent::setUp();

        $this->invoiceRepository = self::resolve(InvoiceRepository::class);
    }

    /**
     * InvoiceRepository->findPrepaidPayment() tests.
     */
    #[Test]
    public function findPrepaidPaymentReturnsNullWhenInvoiceIsNotSetToPaid(): void
    {
        $customer = new CustomerFactory()->createOne();
        $productGroup = new ProductGroupFactory()->hosting()->createOne();
        $product = new ProductFactory()->for($productGroup)->createOne();
        $subscription = new SubscriptionFactory()->for($product)->createOne([
            'customer_id' => $customer->id,
        ]);
        $invoice = new InvoiceFactory()
            ->for($customer)
            ->for($product)
            ->createOne();

        $foundPayment = $this->invoiceRepository->findPrepaidPayment($invoice->paid, $subscription);

        self::assertNull($foundPayment);
    }

    #[Test]
    public function findPrepaidPaymentReturnsNullWhenInvoiceIsSetToPaidButHasNoOrderLinePayment(): void
    {
        $customer = new CustomerFactory()->createOne();
        $productGroup = new ProductGroupFactory()->hosting()->createOne();
        $product = new ProductFactory()->for($productGroup)->createOne();
        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->createOne([
                'customer_id' => $customer->id,
                'product_uuid' => $product->uuid,
            ]);
        $invoice = new InvoiceFactory()
            ->for($customer)
            ->for($product)
            ->createOne([
                'paid' => true,
            ]);
        $order = new OrderFactory()->for($customer)->createOne();
        new OrderLineItemFactory()->createOne([
            'subscription_uuid' => $subscription->uuid,
            'order_id' => $order->id,
        ]);
        $foundPayment = $this->invoiceRepository->findPrepaidPayment($invoice->paid, $subscription);

        self::assertNull($foundPayment);
    }

    #[Test]
    public function findPrepaidPaymentReturnsPaymentWhenInvoiceIsSetToPaidAndHasOrderLinePayment(): void
    {
        $customer = new CustomerFactory()->createOne();
        $productGroup = new ProductGroupFactory()
            ->hosting()
            ->createOne(['uuid' => 'test']);
        $product = new ProductFactory()->for($productGroup)->createOne();
        $subscription = new SubscriptionFactory()->for($customer)->createOne([
            'product_uuid' => $product->uuid,
        ]);
        $invoice = new InvoiceFactory()
            ->for($customer)
            ->for($product)
            ->createOne([
                'paid' => true,
            ]);
        $order = new OrderFactory()->for($customer)->createOne();
        $payment = new PaymentFactory()->createOne([
            'customer_uuid' => $customer->uuid,
            'order_id' => $order->id,
        ]);
        new OrderLineItemFactory()->createOne([
            'subscription_uuid' => $subscription->uuid,
            'order_id' => $order->id,
        ]);
        $foundPaymentId = $this->invoiceRepository->findPrepaidPayment($invoice->paid, $subscription);

        self::assertSame($payment->external_id, $foundPaymentId);
    }

    #[Test]
    public function getNonCreditInvoiceLinesForSubscriptionAndEndDate(): void
    {
        CarbonImmutable::setTestNow($now = CarbonImmutable::now());

        $product = new ProductFactory()->for(new ProductGroupFactory()->extension())->createOne();
        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->for($product)
            ->createOneQuietly([
                'administrative_status' => AdministrativeStatus::ACTIVE->value,
                'end_date' => $now->addMonths(4),
                'next_billing_date' => $now->addMonths(4),
            ]);

        // We are going to fetch invoices for a date 10 days in the future
        $endDate = $now->addDays(10);

        $invoice1 = new InvoiceFactory()
            ->for($subscription)
            ->for($subscription->customer)
            ->for($product)
            ->createOne([
                'net_price' => 11,
                'start_date' => $now->subMonths(1),
                'end_date' => $endDate->addDays(1),
            ]);

        $invoice1Voucher = new InvoiceFactory()
            ->for($subscription)
            ->for($subscription->customer)
            ->for($product)
            ->createOne([
                'net_price' => -1,
                'start_date' => $now->subMonths(1),
                'end_date' => $endDate->addDays(1),
            ]);

        // Invoice with end date same as date we are checking on.
        // Note: The end date is a 'until' date.
        new InvoiceFactory()
            ->for($subscription)
            ->for($subscription->customer)
            ->for($product)
            ->createOne([
                'net_price' => 22,
                'start_date' => $now->subMonths(1),
                'end_date' => $endDate,
            ]);

        $invoice3 = new InvoiceFactory()
            ->for($subscription)
            ->for($subscription->customer)
            ->for($product)
            ->createOne([
                'net_price' => 33,
                'start_date' => $now->subMonths(1),
                'end_date' => $endDate->addDays(1),
            ]);

        // Credit of invoice 3
        new InvoiceFactory()
            ->for($subscription)
            ->for($subscription->customer)
            ->for($product)
            ->createOne([
                'net_price' => -33,
                'start_date' => $now->subMonths(1),
                'end_date' => $endDate->addDays(20),
                'parent_invoice_id' => $invoice3->id,
            ]);

        $invoiceLines = $this->invoiceRepository->getNonCreditInvoiceLinesForSubscriptionAndEndDate(
            $subscription,
            $endDate,
        );

        self::assertCount(3, $invoiceLines);

        self::assertSame(
            [
                $invoice1->id,
                $invoice1Voucher->id,
                $invoice3->id,
            ],
            $invoiceLines->pluck('id')->toArray(),
        );
    }

    #[Test]
    public function getInvoiceForDowngradedSubscription(): void
    {
        $customer = new CustomerFactory()->createOne();
        $productGroup = new ProductGroupFactory()->hosting()->createOne();
        $product = new ProductFactory()->for($productGroup)->createOne();
        $subscription = new SubscriptionFactory()
            ->for($customer)
            ->for($product)
            ->createOne();

        new InvoiceFactory()
            ->for($customer)
            ->for($product)
            ->for($subscription)
            ->createOne(['paid' => true]);

        $translator = self::resolve(TranslatorInterface::class);

        $appendable = $subscription->domain !== null
            ? $translator->translate('invoice.description.for') . " {$subscription->domain}"
            : '';
        $description = sprintf(
            '%s (%s) %s',
            $subscription->product->name,
            ProductChangeType::DOWNGRADE->value,
            $appendable,
        );

        new InvoiceFactory()
            ->for($customer)
            ->for($product)
            ->for($subscription)
            ->createOne([
                'description' => $description,
                'paid' => 0,
            ]);

        $invoice = $this->invoiceRepository->getInvoiceLineForDowngradedSubscription($subscription);
        self::assertNotNull($invoice);
        self::assertSame($invoice->description, $description);
    }

    #[Test]
    public function getInvoiceForDowngradedSubscriptionFailNoInvoiceLine(): void
    {
        $customer = new CustomerFactory()->createOne();
        $productGroup = new ProductGroupFactory()->hosting()->createOne();
        $product = new ProductFactory()->for($productGroup)->createOne();
        $subscription = new SubscriptionFactory()
            ->for($customer)
            ->for($product)
            ->createOne();

        new InvoiceFactory()
            ->for($customer)
            ->for($product)
            ->for($subscription)
            ->createOne(['paid' => true]);

        $invoice = $this->invoiceRepository->getInvoiceLineForDowngradedSubscription($subscription);
        self::assertNull($invoice);
    }

    #[Test]
    public function getUnprocessedInvoiceLinesForCustomerReturnsOnlyNotSentLinesForThatCustomerOrderedByIdDesc(): void
    {
        $customer = new CustomerFactory()->createOne();
        $otherCustomer = new CustomerFactory()->createOne();
        $productGroup = new ProductGroupFactory()->hosting()->createOne();
        $product = new ProductFactory()->for($productGroup)->createOne();

        $unprocessedOne = new InvoiceFactory()
            ->for($customer)
            ->for($product)
            ->createOne(['sent_to_harbor_at' => null]);

        $unprocessedTwo = new InvoiceFactory()
            ->for($customer)
            ->for($product)
            ->createOne(['sent_to_harbor_at' => null]);

        // Already sent to Harbor: must be excluded.
        new InvoiceFactory()
            ->for($customer)
            ->for($product)
            ->createOne(['sent_to_harbor_at' => CarbonImmutable::yesterday()]);

        // Unprocessed but belongs to another customer: must be excluded.
        new InvoiceFactory()
            ->for($otherCustomer)
            ->for($product)
            ->createOne(['sent_to_harbor_at' => null]);

        $invoiceLines = $this->invoiceRepository->getUnprocessedInvoiceLinesForCustomer($customer)->get();

        self::assertSame(
            [$unprocessedTwo->id, $unprocessedOne->id],
            $invoiceLines->pluck('id')->toArray(),
        );
    }

    #[Test]
    public function getUnprocessedInvoiceLinesForCustomerReturnsNothingWhenNoneUnprocessed(): void
    {
        $customer = new CustomerFactory()->createOne();
        $productGroup = new ProductGroupFactory()->hosting()->createOne();
        $product = new ProductFactory()->for($productGroup)->createOne();

        new InvoiceFactory()
            ->for($customer)
            ->for($product)
            ->createOne(['sent_to_harbor_at' => CarbonImmutable::yesterday()]);

        self::assertSame(0, $this->invoiceRepository->getUnprocessedInvoiceLinesForCustomer($customer)->count());
    }

    #[Test]
    public function findForCustomerByIdsReturnsOnlyLinesBelongingToTheGivenCustomer(): void
    {
        $customer = new CustomerFactory()->createOne();
        $otherCustomer = new CustomerFactory()->createOne();
        $productGroup = new ProductGroupFactory()->hosting()->createOne();
        $product = new ProductFactory()->for($productGroup)->createOne();

        $ownLine = new InvoiceFactory()
            ->for($customer)
            ->for($product)
            ->createOne(['sent_to_harbor_at' => null]);

        $otherCustomersLine = new InvoiceFactory()
            ->for($otherCustomer)
            ->for($product)
            ->createOne(['sent_to_harbor_at' => null]);

        $invoiceLines = $this->invoiceRepository->findForCustomerByIds(
            $customer,
            [$ownLine->id, $otherCustomersLine->id],
        );

        self::assertSame([$ownLine->id], $invoiceLines->pluck('id')->toArray());
    }

    #[Test]
    public function findForCustomerByIdsIncludesLinesAlreadySentToHarbor(): void
    {
        $customer = new CustomerFactory()->createOne();
        $productGroup = new ProductGroupFactory()->hosting()->createOne();
        $product = new ProductFactory()->for($productGroup)->createOne();

        $alreadySentLine = new InvoiceFactory()
            ->for($customer)
            ->for($product)
            ->createOne(['sent_to_harbor_at' => CarbonImmutable::yesterday()]);

        $invoiceLines = $this->invoiceRepository->findForCustomerByIds($customer, [$alreadySentLine->id]);

        self::assertSame([$alreadySentLine->id], $invoiceLines->pluck('id')->toArray());
    }

    #[Test]
    public function findForCustomerByIdsReturnsEmptyCollectionForUnknownIds(): void
    {
        $customer = new CustomerFactory()->createOne();

        $invoiceLines = $this->invoiceRepository->findForCustomerByIds($customer, [696969]);

        self::assertCount(0, $invoiceLines);
    }

    #[Test]
    public function getLatestPaidInvoiceLineForDowngradedSubscription(): void
    {
        $customer = new CustomerFactory()->createOne();
        $productGroup = new ProductGroupFactory()->hosting()->createOne();
        $product = new ProductFactory()->for($productGroup)->createOne();
        $subscription = new SubscriptionFactory()
            ->for($customer)
            ->for($product)
            ->createOne();

        new InvoiceFactory()
            ->for($customer)
            ->for($product)
            ->for($subscription)
            ->createOne([
                'paid' => true,
                'sent_to_harbor_at' => CarbonImmutable::yesterday(),
            ]);

        new InvoiceFactory()
            ->for($customer)
            ->for($product)
            ->for($subscription)
            ->createOne([
                'paid' => true,
                'sent_to_harbor_at' => CarbonImmutable::yesterday(),
            ]);

        new InvoiceFactory()
            ->for($customer)
            ->for($product)
            ->for($subscription)
            ->createOne([
                'paid' => true,
                'sent_to_harbor_at' => null,
            ]);

        new InvoiceFactory()
            ->for($customer)
            ->for($product)
            ->for($subscription)
            ->createOne([
                'paid' => true,
                'sent_to_harbor_at' => null,
            ]);

        $invoice = $this->invoiceRepository->getLatestPaidInvoiceLineForDowngradedSubscription($subscription);
        self::assertNotNull($invoice);
        self::assertNotNull($invoice->sent_to_harbor_at);
        self::assertTrue($invoice->paid);
    }
}
