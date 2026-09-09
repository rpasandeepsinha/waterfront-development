<?php

declare(strict_types=1);

namespace Tests\Domain\Harbor\Services\Invoice\InvoiceCreator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\InvoiceFactory;
use Tests\Factories\ProductFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Harbor\DTO\Services\Invoice\InvoiceCreator\CreateInvoiceFromResult;

#[CoversClass(CreateInvoiceFromResult::class)]
class CreateInvoiceFromResultTest extends IntegrationTestCase
{
    #[Test]
    public function test(): void
    {
        $customer = CustomerFactory::new()->createOne();
        $product = new ProductFactory()->nlDomain()->createOne();
        $invoices = [
            new InvoiceFactory()
                ->for($customer)
                ->for($product)
                ->createOne(),
            new InvoiceFactory()
                ->for($customer)
                ->for($product)
                ->createOne(),
        ];
        $result = new CreateInvoiceFromResult(...$invoices);

        self::assertSame($invoices[0]->id, $result->getOriginalInvoice()->id);
        self::assertSame($invoices[1]->id, $result->getNewInvoice()->id);
    }
}
