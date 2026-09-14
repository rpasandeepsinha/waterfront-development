<?php

declare(strict_types=1);

namespace Tests\Domain\Harbor\Services\Invoice;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\InvoiceFactory;
use Tests\Factories\ProductFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Harbor\DTO\Services\Invoice\InvoiceCrediter\InvoiceToCredit;
use Waterfront\Domain\Harbor\Services\Invoice\InvoiceCrediter;

#[CoversClass(InvoiceCrediter::class)]
class InvoiceCrediterTest extends IntegrationTestCase
{
    private InvoiceCrediter $invoiceCrediter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->invoiceCrediter = self::resolve(InvoiceCrediter::class);
    }

    #[DataProvider('creditDataProvider')]
    #[Test]
    public function crediting(
        int $originalInvoicePrice,
        int $amountToCredit,
        int $creditOffsetDays,
        int $expectedCreditInvoiceAmount,
    ): void {
        $now = CarbonImmutable::now();
        CarbonImmutable::setTestNow($now);
        $startDate = $now->subMonths(5);
        $endDate = $now->addMonths(7);
        $expectedCreditStartDate = $now->addDays($creditOffsetDays);

        $product = new ProductFactory()->nlDomain()->createOne();
        $debitInvoice = new InvoiceFactory()
            ->withCustomer()
            ->for($product)
            ->createOne([
                'start_date' => $startDate,
                'end_date' => $endDate,
                'net_price' => $originalInvoicePrice,
                'gross_price' => $originalInvoicePrice,
                'paid' => true,
                'prepaid_reference' => 'fake-prepaid-reference',
            ]);

        $creditResult = $this->invoiceCrediter->credit(
            new InvoiceToCredit(
                invoice: $debitInvoice,
                amountToCredit: $amountToCredit,
                creditStartDate: $expectedCreditStartDate,
            ),
        );
        $creditInvoice = $creditResult->getCreditInvoice();

        // These should be changed.
        self::assertSame($expectedCreditInvoiceAmount, $creditInvoice->net_price);
        self::assertSame($expectedCreditInvoiceAmount, $creditInvoice->gross_price);
        self::assertFalse($creditInvoice->paid);
        self::assertNull($creditInvoice->prepaid_reference);
        self::assertSame($debitInvoice->id, $creditInvoice->parent_invoice_id);

        // These should remain untouched.
        self::assertSame($debitInvoice->customer_id, $creditInvoice->customer_id);
        self::assertSame($debitInvoice->product_id, $creditInvoice->product_id);
        self::assertSame($expectedCreditStartDate->timestamp, $creditInvoice->start_date->timestamp);
        self::assertSame($debitInvoice->end_date->timestamp, $creditInvoice->end_date->timestamp);
        self::assertSame($debitInvoice->period, $creditInvoice->period);
        self::assertSame($debitInvoice->vat_code, $creditInvoice->vat_code);
        self::assertSame($debitInvoice->vat_rate, $creditInvoice->vat_rate);
        self::assertSame($debitInvoice->ledger_code, $creditInvoice->ledger_code);
    }

    /**
     * @return iterable<string, mixed>
     */
    public static function creditDataProvider(): iterable
    {
        yield 'with a credit invoice for the full amount of the original debit.' => [
            'originalInvoicePrice' => 1,
            'amountToCredit' => 1,
            'creditOffsetDays' => 0, // 0 = now
            'expectedCreditInvoiceAmount' => -1,
        ];
        yield 'with a credit invoice that credits less than the original debit has.' => [
            'originalInvoicePrice' => 2,
            'amountToCredit' => 1,
            'creditOffsetDays' => 0,
            'expectedCreditInvoiceAmount' => -1,
        ];
        yield 'with a credit invoice for the full amount of the original credit.' => [
            'originalInvoicePrice' => -1,
            'amountToCredit' => -1,
            'creditOffsetDays' => 0,
            'expectedCreditInvoiceAmount' => 1,
        ];
        yield 'with a credit invoice that credits less than the original credit has.' => [
            'originalInvoicePrice' => -2,
            'amountToCredit' => -1,
            'creditOffsetDays' => 0,
            'expectedCreditInvoiceAmount' => 1,
        ];
        yield 'with an explicit credit start date.' => [
            'originalInvoicePrice' => -2,
            'amountToCredit' => -1,
            'creditOffsetDays' => -20, // select 20 days back as credit date
            'expectedCreditInvoiceAmount' => 1,
        ];
    }

    #[DataProvider('mergeOnPdfInvoiceDataProvider')]
    #[Test]
    public function mergeOnPdfInvoice(
        bool $originalHasMergeOnPdfInvoice,
        bool $originalMergeOnPdfInvoiceIsCredited,
        bool $expectsMergeOnPdfInvoiceIdOnCredit,
    ): void {
        $mergeOnPdfInvoice = null;
        $product = new ProductFactory()->nlDomain()->createOne();

        $customer = CustomerFactory::new()->createOne();

        if ($originalHasMergeOnPdfInvoice) {
            $mergeOnPdfInvoice = new InvoiceFactory()
                ->for($customer)
                ->for($product)
                ->createOne();
        }

        $creditedMergeOnPdfInvoice = null;
        if ($originalMergeOnPdfInvoiceIsCredited) {
            $creditedMergeOnPdfInvoice = new InvoiceFactory()
                ->for($customer)
                ->for($product)
                ->createOne(['parent_invoice_id' => $mergeOnPdfInvoice?->id]);
        }

        $debitInvoice = new InvoiceFactory()
            ->for($customer)
            ->for($product)
            ->createOne(['merge_on_pdf_with_invoice_id' => $mergeOnPdfInvoice?->id]);

        $creditResult = $this->invoiceCrediter->credit(
            new InvoiceToCredit(
                invoice: $debitInvoice,
            ),
        );
        $creditInvoice = $creditResult->getCreditInvoice();

        // If the original target merge on invoice is credited as well, we expect that id on the credit invoice.
        if ($expectsMergeOnPdfInvoiceIdOnCredit) {
            self::assertNotNull($creditInvoice->merge_on_pdf_with_invoice_id);
            self::assertSame($creditedMergeOnPdfInvoice?->id, $creditInvoice->merge_on_pdf_with_invoice_id);
        } else {
            self::assertNull($creditInvoice->merge_on_pdf_with_invoice_id);
        }
    }

    /**
     * @return iterable<string, mixed>
     */
    public static function mergeOnPdfInvoiceDataProvider(): iterable
    {
        yield 'no merge on pdf invoice' => [
            'originalHasMergeOnPdfInvoice' => false,
            'originalMergeOnPdfInvoiceIsCredited' => false,
            'expectsMergeOnPdfInvoiceIdOnCredit' => false,
        ];

        yield 'merge on pdf invoice, but not credited' => [
            'originalHasMergeOnPdfInvoice' => true,
            'originalMergeOnPdfInvoiceIsCredited' => false,
            'expectsMergeOnPdfInvoiceIdOnCredit' => false,
        ];

        yield 'merge on pdf invoice, and credited' => [
            'originalHasMergeOnPdfInvoice' => true,
            'originalMergeOnPdfInvoiceIsCredited' => true,
            'expectsMergeOnPdfInvoiceIdOnCredit' => true,
        ];
    }
}
