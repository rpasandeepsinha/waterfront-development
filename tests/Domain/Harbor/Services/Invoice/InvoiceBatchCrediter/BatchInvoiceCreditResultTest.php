<?php

declare(strict_types=1);

namespace Tests\Domain\Harbor\Services\Invoice\InvoiceBatchCrediter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\InvoiceFactory;
use Tests\Factories\ProductFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Harbor\DTO\Services\Invoice\InvoiceBatchCrediter\BatchInvoiceCreditResult;

#[CoversClass(BatchInvoiceCreditResult::class)]
class BatchInvoiceCreditResultTest extends IntegrationTestCase
{
    #[Test]
    public function test(): void
    {
        $customer = CustomerFactory::new()->createOne();
        $product = new ProductFactory()->nlDomain()->createOne();
        $newInvoice = new InvoiceFactory()->for($customer)->for($product)->createOne();
        $creditInvoice = new InvoiceFactory()->for($customer)->for($product)->createOne();

        $result = new BatchInvoiceCreditResult([$creditInvoice], [$newInvoice]);
        $creditInvoices = $result->getCreditInvoices();
        $newInvoices = $result->getNewInvoices();

        self::assertCount(1, $creditInvoices);
        self::assertCount(1, $newInvoices);

        $resultCreditInvoice = reset($creditInvoices);
        $resultNewInvoice = reset($newInvoices);

        self::assertSame($creditInvoice->id, $resultCreditInvoice->id);
        self::assertSame($newInvoice->id, $resultNewInvoice->id);
    }
}
