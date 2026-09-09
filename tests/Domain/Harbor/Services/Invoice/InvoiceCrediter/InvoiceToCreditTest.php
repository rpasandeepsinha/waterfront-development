<?php

declare(strict_types=1);

namespace Tests\Domain\Harbor\Services\Invoice\InvoiceCrediter;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use SandwaveIo\HarborMessages\Message\Enum\InvoiceLineCreditReason;
use Tests\Factories\InvoiceFactory;
use Tests\Factories\ProductFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Harbor\DTO\Services\Invoice\InvoiceCrediter\InvoiceToCredit;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelReason;

#[CoversClass(InvoiceToCredit::class)]
class InvoiceToCreditTest extends IntegrationTestCase
{
    #[Test]
    public function getInvoice(): void
    {
        $product = new ProductFactory()->nlDomain()->createOne();
        $invoice = new InvoiceFactory()
            ->withCustomer()
            ->for($product)
            ->createOne();

        $invoiceToCredit = new InvoiceToCredit($invoice);
        self::assertSame($invoice->id, $invoiceToCredit->getInvoice()->id);
    }

    #[DataProvider('getAmountToCreditDataProvider')]
    #[Test]
    public function getAmountToCredit(
        int $invoiceLineNetPrice,
        ?int $amountToCredit,
        ?string $expectedException,
        ?int $expectedAmountToCredit,
    ): void {
        $product = new ProductFactory()->nlDomain()->createOne();
        $invoice = new InvoiceFactory()
            ->withCustomer()
            ->for($product)
            ->createOne([
                'net_price' => $invoiceLineNetPrice,
            ]);

        if ($expectedException !== null) {
            /** @phpstan-ignore-next-line  */
            self::expectException($expectedException);
        }

        $invoiceToCredit = new InvoiceToCredit($invoice, $amountToCredit);

        if ($expectedException !== null) {
            return;
        }

        if ($expectedAmountToCredit !== null) {
            self::assertSame($expectedAmountToCredit, $invoiceToCredit->getAmountToCredit());
        }
    }

    #[Test]
    public function shouldCreateNewInvoice(): void
    {
        $product = new ProductFactory()->nlDomain()->createOne();
        $invoice = new InvoiceFactory()
            ->withCustomer()
            ->for($product)
            ->createOne();

        $invoiceToCredit = new InvoiceToCredit($invoice, shouldCreateNewInvoice: true);
        self::assertTrue($invoiceToCredit->shouldCreateNewInvoice());

        // Test for the default parameter value.
        $invoiceToCredit = new InvoiceToCredit($invoice);
        self::assertFalse($invoiceToCredit->shouldCreateNewInvoice());
    }

    /**
     * @return iterable<string, mixed>
     */
    public static function getAmountToCreditDataProvider(): iterable
    {
        yield 'with full amount of the original invoice line' => [
            'invoiceLineNetPrice' => 10,
            'amountToCredit' => 10,
            'expectedException' => null,
            'expectedAmountToCredit' => 10,
        ];

        yield 'with a partial amount of the original invoice line' => [
            'invoiceLineNetPrice' => 10,
            'amountToCredit' => 5,
            'expectedException' => null,
            'expectedAmountToCredit' => 5,
        ];

        yield 'applies full amount by default when amount to credit is passed as null' => [
            'invoiceLineNetPrice' => 10,
            'amountToCredit' => null,
            'expectedException' => null,
            'expectedAmountToCredit' => 10,
        ];

        yield 'with full amount of the original negative invoice line' => [
            'invoiceLineNetPrice' => -10,
            'amountToCredit' => -10,
            'expectedException' => null,
            'expectedAmountToCredit' => -10,
        ];

        yield 'with a partial amount of the original negative invoice line' => [
            'invoiceLineNetPrice' => -10,
            'amountToCredit' => -5,
            'expectedException' => null,
            'expectedAmountToCredit' => -5,
        ];

        yield 'with an invoice line where net_price === 0 and amount to credit === 0 is allowed' => [
            'invoiceLineNetPrice' => 0,
            'amountToCredit' => 0,
            'expectedException' => null,
            'expectedAmountToCredit' => 0,
        ];

        yield 'with an invoice line where net_price > 0 and amount to credit === 0 throws exception' => [
            'invoiceLineNetPrice' => 1,
            'amountToCredit' => 0,
            'expectedException' => InvalidArgumentException::class,
            'expectedAmountToCredit' => null,
        ];

        yield 'with an amount to credit that exceeds the debit invoice line net_price throws exception' => [
            'invoiceLineNetPrice' => 1,
            'amountToCredit' => 2,
            'expectedException' => InvalidArgumentException::class,
            'expectedAmountToCredit' => null,
        ];

        yield 'with an amount to credit that exceeds the credit invoice line net_price throws exception' => [
            'invoiceLineNetPrice' => -1,
            'amountToCredit' => -2,
            'expectedException' => InvalidArgumentException::class,
            'expectedAmountToCredit' => null,
        ];

        yield 'cannot credit negative amount for debit invoice line' => [
            'invoiceLineNetPrice' => 1,
            'amountToCredit' => -1,
            'expectedException' => InvalidArgumentException::class,
            'expectedAmountToCredit' => null,
        ];

        yield 'cannot credit positive amount for credit invoice line' => [
            'invoiceLineNetPrice' => -1,
            'amountToCredit' => 1,
            'expectedException' => InvalidArgumentException::class,
            'expectedAmountToCredit' => null,
        ];
    }

    #[Test]
    public function getCreditStartDateShouldDependOnGivenCreditDate(): void
    {
        $now = CarbonImmutable::now();
        $startDate = $now->subMonths(5);
        $endDate = $now->addMonths(7);
        $product = new ProductFactory()->nlDomain()->createOne();
        $invoice = new InvoiceFactory()
            ->withCustomer()
            ->for($product)
            ->createOne([
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);

        // Test without given credit start date should fall back to invoice start date
        $invoiceToCredit = new InvoiceToCredit($invoice);
        self::assertSame($startDate->timestamp, $invoiceToCredit->getCreditStartDate()->timestamp);

        // Test with given credit start date should give back that date
        $invoiceToCredit = new InvoiceToCredit($invoice, creditStartDate: $now);
        self::assertSame($now->timestamp, $invoiceToCredit->getCreditStartDate()->timestamp);
    }

    #[Test]
    public function creditableCancelReasonHasValidCreditReasonMapping(): void
    {
        foreach (SubscriptionCancelReason::cases() as $cancelReason) {
            if ($cancelReason->allowedToCredit()) {
                self::assertNotNull(InvoiceLineCreditReason::tryFrom($cancelReason->value), $cancelReason->value);
            } else {
                self::assertNull(InvoiceLineCreditReason::tryFrom($cancelReason->value), $cancelReason->value);
            }
        }
    }
}
