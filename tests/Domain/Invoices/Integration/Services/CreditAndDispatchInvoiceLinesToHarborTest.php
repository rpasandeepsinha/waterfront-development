<?php

declare(strict_types=1);

namespace Tests\Domain\Invoices\Integration\Services;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Tests\Factories\CustomerFactory;
use Tests\Factories\InvoiceFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Harbor\DTO\Services\Invoice\InvoiceBatchCrediter\InvoiceToCreditBatch;
use Waterfront\Domain\Harbor\DTO\Services\Invoice\InvoiceCrediter\InvoiceToCredit;
use Waterfront\Domain\Harbor\Services\HarborApiClient\HarborApi;
use Waterfront\Domain\Harbor\Services\Invoice\InvoiceBatchCrediter;
use Waterfront\Domain\Harbor\Services\Invoice\InvoiceModifier;
use Waterfront\Domain\Harbor\Services\Message\MessageService;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Domain\Invoices\Services\CreditAndDispatchInvoiceLinesToHarbor;
use Waterfront\Domain\Subscriptions\Exceptions\Services\SubscriptionCrediter\MultipleCustomersException;
use Waterfront\Domain\Subscriptions\Exceptions\Services\SubscriptionCrediter\UnsentInvoiceLineException;

#[CoversClass(CreditAndDispatchInvoiceLinesToHarbor::class)]
class CreditAndDispatchInvoiceLinesToHarborTest extends IntegrationTestCase
{
    private CreditAndDispatchInvoiceLinesToHarbor $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new CreditAndDispatchInvoiceLinesToHarbor(
            self::resolve(InvoiceBatchCrediter::class),
            self::resolve(InvoiceModifier::class),
            self::resolve(MessageService::class),
            self::createStub(HarborApi::class),
            self::resolve(LoggerInterface::class),
        );
    }

    #[Test]
    public function createsACreditVariationOfEachNonCreditedDebitInvoiceLineForEverySubscription(): void
    {
        $now = CarbonImmutable::now();
        CarbonImmutable::setTestNow($now);

        $customer = new CustomerFactory()->withAddress()->withFinancialContact()->createOne();

        $group = new ProductGroupFactory()->hosting()->createOne();
        $product = new ProductFactory()->for($group)->createOne();

        $firstSubscription = new SubscriptionFactory()
            ->for($product)
            ->for($customer)
            ->createOne();
        $secondSubscription = new SubscriptionFactory()
            ->for($product)
            ->for($customer)
            ->createOne();

        $firstParentInvoice = new InvoiceFactory()
            ->for($customer)
            ->for($firstSubscription)
            ->for($firstSubscription->product)
            ->createOne([
                'net_price' => 123,
                'start_date' => $now->subDay(),
                'end_date' => $now->subDay()->addYear(),
                'sent_to_harbor_at' => $now,
            ]);
        $secondParentInvoice = new InvoiceFactory()
            ->for($customer)
            ->for($secondSubscription)
            ->for($secondSubscription->product)
            ->createOne([
                'net_price' => 123,
                'start_date' => $now->subMonths(6),
                'end_date' => $now->addMonths(6),
                'sent_to_harbor_at' => $now,
            ]);
        $thirdParentInvoice = new InvoiceFactory()
            ->for($customer)
            ->for($secondSubscription)
            ->for($secondSubscription->product)
            ->createOne([
                'net_price' => 123,
                'start_date' => $now,
                'end_date' => $now->addYear(),
                'sent_to_harbor_at' => $now,
            ]);

        $creditBatch = $this->createCreditBatch([$firstParentInvoice, $secondParentInvoice, $thirdParentInvoice]);

        $this->service->creditAndDispatch($creditBatch);

        self::assertCount(3, Invoice::where('net_price', '<', 0)->get()->all());

        $firstCredit = Invoice::where('net_price', '<', 0)
            ->where('parent_invoice_id', $firstParentInvoice->id)
            ->where('subscription_id', $firstSubscription->id)
            ->firstOrFail();
        self::assertSame(-50, $firstCredit->net_price);

        $secondCredit = Invoice::where('net_price', '<', 0)
            ->where('parent_invoice_id', $secondParentInvoice->id)
            ->where('subscription_id', $secondSubscription->id)
            ->firstOrFail();
        self::assertSame(-50, $secondCredit->net_price);

        $thirdCredit = Invoice::where('net_price', '<', 0)
            ->where('parent_invoice_id', $thirdParentInvoice->id)
            ->where('subscription_id', $secondSubscription->id)
            ->firstOrFail();
        self::assertSame(-50, $thirdCredit->net_price);
    }

    #[Test]
    public function throwsExceptionIfPassedSubscriptionsHaveMixedCustomers(): void
    {
        $now = CarbonImmutable::now();
        CarbonImmutable::setTestNow($now);

        $firstCustomer = new CustomerFactory()->withAddress()->withFinancialContact()->createOne();

        $secondCustomer = new CustomerFactory()->withAddress()->withFinancialContact()->createOne();

        $group = new ProductGroupFactory()->hosting()->createOne();
        $product = new ProductFactory()->for($group)->createOne();

        $firstSubscription = new SubscriptionFactory()
            ->for($product)
            ->for($firstCustomer)
            ->createOne();
        $secondSubscription = new SubscriptionFactory()
            ->for($product)
            ->for($secondCustomer)
            ->createOne();
        $thirdSubscription = new SubscriptionFactory()
            ->for($product)
            ->for($firstCustomer)
            ->createOne();

        $firstParentInvoice = new InvoiceFactory()
            ->for($firstCustomer)
            ->for($firstSubscription)
            ->for($firstSubscription->product)
            ->createOne([
                'net_price' => 123,
                'start_date' => $now->subDay(),
                'end_date' => $now->subDay()->addYear(),
                'sent_to_harbor_at' => $now,
            ]);
        $secondParentInvoice = new InvoiceFactory()
            ->for($secondCustomer)
            ->for($secondSubscription)
            ->for($secondSubscription->product)
            ->createOne([
                'net_price' => 123,
                'start_date' => $now->subDay(),
                'end_date' => $now->subDay()->addYear(),
                'sent_to_harbor_at' => $now,
            ]);
        $thirdParentInvoice = new InvoiceFactory()
            ->for($firstCustomer)
            ->for($thirdSubscription)
            ->for($thirdSubscription->product)
            ->createOne([
                'net_price' => 123,
                'start_date' => $now->subDay(),
                'end_date' => $now->subDay()->addYear(),
                'sent_to_harbor_at' => $now,
            ]);

        $creditBatch = $this->createCreditBatch([$firstParentInvoice, $secondParentInvoice, $thirdParentInvoice]);

        $this->expectException(MultipleCustomersException::class);

        $this->service->creditAndDispatch($creditBatch);
    }

    #[Test]
    public function throwsExceptionIfOneOfTheEligibleDebitInvoiceLinesHasYetToBeSentToHarbor(): void
    {
        $now = CarbonImmutable::now();
        CarbonImmutable::setTestNow($now);

        $customer = new CustomerFactory()->withAddress()->withFinancialContact()->createOne();

        $group = new ProductGroupFactory()->hosting()->createOne();
        $product = new ProductFactory()->for($group)->createOne();

        $firstSubscription = new SubscriptionFactory()
            ->for($product)
            ->for($customer)
            ->createOne();
        $secondSubscription = new SubscriptionFactory()
            ->for($product)
            ->for($customer)
            ->createOne();
        $thirdSubscription = new SubscriptionFactory()
            ->for($product)
            ->for($customer)
            ->createOne();

        $firstParentInvoice = new InvoiceFactory()
            ->for($customer)
            ->for($firstSubscription)
            ->for($firstSubscription->product)
            ->createOne([
                'net_price' => 123,
                'start_date' => $now->subDay(),
                'end_date' => $now->subDay()->addYear(),
                'sent_to_harbor_at' => $now,
            ]);
        $secondParentInvoice = new InvoiceFactory()
            ->for($customer)
            ->for($secondSubscription)
            ->for($secondSubscription->product)
            ->createOne([
                'net_price' => 123,
                'start_date' => $now->subDay(),
                'end_date' => $now->subDay()->addYear(),
                'sent_to_harbor_at' => null,
            ]);
        $thirdParentInvoice = new InvoiceFactory()
            ->for($customer)
            ->for($thirdSubscription)
            ->for($thirdSubscription->product)
            ->createOne([
                'net_price' => 123,
                'start_date' => $now->subDay(),
                'end_date' => $now->subDay()->addYear(),
                'sent_to_harbor_at' => $now,
            ]);

        $creditBatch = $this->createCreditBatch([$firstParentInvoice, $secondParentInvoice, $thirdParentInvoice]);

        $this->expectException(UnsentInvoiceLineException::class);

        $this->service->creditAndDispatch($creditBatch);
    }

    /**
     * @param array<int, Invoice> $invoiceLines
     */
    private function createCreditBatch(array $invoiceLines): InvoiceToCreditBatch
    {
        $invoiceToCreditBatch = new InvoiceToCreditBatch();
        foreach ($invoiceLines as $invoiceLine) {
            $invoiceToCreditBatch->add(
                new InvoiceToCredit($invoiceLine, 50),
            );
        }

        return $invoiceToCreditBatch;
    }
}
