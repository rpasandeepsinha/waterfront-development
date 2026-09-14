<?php

declare(strict_types=1);

namespace Tests\Domain\Harbor\Services\Invoice\InvoiceBatchCrediter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\InvoiceFactory;
use Tests\Factories\ProductFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Harbor\DTO\Services\Invoice\InvoiceBatchCrediter\InvoiceToCreditBatch;
use Waterfront\Domain\Harbor\DTO\Services\Invoice\InvoiceCrediter\InvoiceToCredit;

#[CoversClass(InvoiceToCreditBatch::class)]
class InvoiceToCreditBatchTest extends IntegrationTestCase
{
    #[Test]
    public function test(): void
    {
        $customer = CustomerFactory::new()->createOne();
        $product = new ProductFactory()->nlDomain()->createOne();
        $invoiceWithoutNew = new InvoiceFactory()
            ->for($customer)
            ->for($product)
            ->createOne();
        $invoiceWithNew = new InvoiceFactory()
            ->for($customer)
            ->for($product)
            ->createOne();

        $toTestBatch = new InvoiceToCreditBatch([
            new InvoiceToCredit($invoiceWithNew, shouldCreateNewInvoice: true),
            new InvoiceToCredit($invoiceWithoutNew),
        ]);

        $invoicesToCredit = $toTestBatch->getInvoicesToCredit();
        self::assertCount(2, $invoicesToCredit);

        $invoicesForNew = $toTestBatch->getInvoicesForNew();
        self::assertCount(1, $invoicesForNew);
        $invoiceForNew = $invoicesForNew[0]->getInvoice();
        self::assertSame($invoiceWithNew->id, $invoiceForNew->id);
    }
}
