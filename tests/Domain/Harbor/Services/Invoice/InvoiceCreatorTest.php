<?php

declare(strict_types=1);

namespace Tests\Domain\Harbor\Services\Invoice;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\InvoiceFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Services\CustomerVatService;
use Waterfront\Domain\Harbor\Services\Invoice\InvoiceCreator;

#[CoversClass(InvoiceCreator::class)]
class InvoiceCreatorTest extends IntegrationTestCase
{
    private InvoiceCreator $invoiceCreator;

    private CustomerVatService $vatService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->invoiceCreator = self::resolve(InvoiceCreator::class);
        $this->vatService = self::resolve(CustomerVatService::class);
    }

    #[Test]
    public function batchCreateFromExisting(): void
    {
        $customer = new CustomerFactory()->createOne();
        $productGroup = new ProductGroupFactory()->hosting()->createOne();
        $product = new ProductFactory()->for($productGroup)->createOne();
        $subscription = new SubscriptionFactory()
            ->for($product)
            ->for($customer)
            ->createOne();

        $customerVatDto = $this->vatService->getCustomerVatData($customer);

        $existingOutdatedInvoice = new InvoiceFactory()
            ->for($customer)
            ->for($subscription)
            ->for($product)
            ->createOne([
                'prepaid_reference' => 'fake-reference',
                'start_date' => CarbonImmutable::create(2001),
                'end_date' => CarbonImmutable::create(2002),
            ]);

        $existingUpToDateInvoice = new InvoiceFactory()
            ->for($customer)
            ->for($subscription)
            ->for($product)
            ->createOne([
                'start_date' => CarbonImmutable::create(1337),
                'end_date' => CarbonImmutable::create(1337),
            ]);

        $batchCreateResult = $this->invoiceCreator->batchCreateFromExisting([
            $existingOutdatedInvoice,
            $existingUpToDateInvoice,
        ]);
        [$newFromOutdatedInvoice, $newFromUpToDateInvoice] = $batchCreateResult->getNewInvoices();

        // Let's first test whether the outdated original invoice line was properly reflected to the new invoice line.
        self::assertSame($existingOutdatedInvoice->net_price, $newFromOutdatedInvoice->net_price);
        self::assertSame($existingOutdatedInvoice->gross_price, $newFromOutdatedInvoice->gross_price);
        self::assertSame($customer->id, $newFromOutdatedInvoice->customer_id);
        self::assertSame($existingOutdatedInvoice->product_id, $newFromOutdatedInvoice->product_id);
        self::assertSame(
            $existingOutdatedInvoice->start_date->timestamp,
            $newFromOutdatedInvoice->start_date->timestamp,
        );
        self::assertSame(
            $existingOutdatedInvoice->end_date->timestamp,
            $newFromOutdatedInvoice->end_date->timestamp,
        );
        self::assertSame($existingOutdatedInvoice->period, $newFromOutdatedInvoice->period);
        self::assertSame($customerVatDto->vatCode, $newFromOutdatedInvoice->vat_code);
        self::assertSame($customerVatDto->vatRate, $newFromOutdatedInvoice->vat_rate);
        self::assertSame($existingOutdatedInvoice->ledger_code, $newFromOutdatedInvoice->ledger_code);
        self::assertSame($existingOutdatedInvoice->title, $newFromOutdatedInvoice->title);
        self::assertSame($existingOutdatedInvoice->description, $newFromOutdatedInvoice->description);
        self::assertSame($existingOutdatedInvoice->group_label, $newFromOutdatedInvoice->group_label);
        self::assertSame($existingOutdatedInvoice->type, $newFromOutdatedInvoice->type);
        self::assertNull($newFromOutdatedInvoice->prepaid_reference);
        self::assertFalse($newFromOutdatedInvoice->paid);

        // Test whether the up-to-date invoice line barely contains any changes on its newer counterpart.
        self::assertSame($existingUpToDateInvoice->net_price, $newFromUpToDateInvoice->net_price);
        self::assertSame($existingUpToDateInvoice->gross_price, $newFromUpToDateInvoice->gross_price);
        self::assertSame($existingUpToDateInvoice->customer_id, $newFromUpToDateInvoice->customer_id);
        self::assertSame($existingUpToDateInvoice->product_id, $newFromUpToDateInvoice->product_id);
        self::assertSame(
            $existingUpToDateInvoice->start_date->timestamp,
            $newFromUpToDateInvoice->start_date->timestamp,
        );
        self::assertSame($existingUpToDateInvoice->end_date->timestamp, $newFromUpToDateInvoice->end_date->timestamp);
        self::assertSame($existingUpToDateInvoice->period, $newFromUpToDateInvoice->period);
        self::assertSame($existingUpToDateInvoice->vat_code, $newFromUpToDateInvoice->vat_code);
        self::assertSame($existingUpToDateInvoice->vat_rate, $newFromUpToDateInvoice->vat_rate);
        self::assertSame($existingUpToDateInvoice->ledger_code, $newFromUpToDateInvoice->ledger_code);
        self::assertSame($existingUpToDateInvoice->title, $newFromUpToDateInvoice->title);
        self::assertSame($existingUpToDateInvoice->description, $newFromUpToDateInvoice->description);
        self::assertSame($existingUpToDateInvoice->group_label, $newFromUpToDateInvoice->group_label);
        self::assertSame($existingUpToDateInvoice->type, $newFromUpToDateInvoice->type);
        self::assertNull($newFromUpToDateInvoice->prepaid_reference);
        self::assertFalse($newFromUpToDateInvoice->paid);
    }
}
