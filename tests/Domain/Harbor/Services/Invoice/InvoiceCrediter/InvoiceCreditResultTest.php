<?php

declare(strict_types=1);

namespace Tests\Domain\Harbor\Services\Invoice\InvoiceCrediter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\InvoiceFactory;
use Tests\Factories\ProductFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Harbor\DTO\Services\Invoice\InvoiceCrediter\InvoiceCreditResult;

#[CoversClass(InvoiceCreditResult::class)]
class InvoiceCreditResultTest extends IntegrationTestCase
{
    #[Test]
    public function test(): void
    {
        $product = new ProductFactory()->nlDomain()->createOne();
        $invoices = [
            new InvoiceFactory()
                ->withCustomer()
                ->for($product)
                ->createOne(),
            new InvoiceFactory()
                ->withCustomer()
                ->for($product)
                ->createOne(),
        ];
        $result = new InvoiceCreditResult(...$invoices);

        $originalInvoice = $result->getOriginalInvoice();
        $creditInvoice = $result->getCreditInvoice();

        self::assertSame($invoices[0]->id, $originalInvoice->id);
        self::assertSame($invoices[1]->id, $creditInvoice->id);
    }
}
