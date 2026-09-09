<?php

declare(strict_types=1);

namespace Tests\Domain\Harbor\Services\Invoice\InvoiceCreator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\InvoiceFactory;
use Tests\Factories\ProductFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Harbor\DTO\Services\Invoice\InvoiceCreator\BatchCreateFromExistingResult;
use Waterfront\Domain\Harbor\DTO\Services\Invoice\InvoiceCreator\CreateInvoiceFromResult;

#[CoversClass(BatchCreateFromExistingResult::class)]
class BatchCreateFromExistingResultTest extends IntegrationTestCase
{
    #[Test]
    public function getResults(): void
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
        $bulkResult = new BatchCreateFromExistingResult([new CreateInvoiceFromResult(...$invoices)]);

        $results = $bulkResult->getResults();
        self::assertCount(1, $results);

        $result = reset($results);
        self::assertSame($invoices[0]->id, $result->getOriginalInvoice()->id);
        self::assertSame($invoices[1]->id, $result->getNewInvoice()->id);
    }

    #[Test]
    public function getAllNewInvoices(): void
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
        $bulkResult = new BatchCreateFromExistingResult([new CreateInvoiceFromResult(...$invoices)]);

        $newInvoices = $bulkResult->getNewInvoices();
        self::assertCount(1, $newInvoices);

        $newInvoice = reset($newInvoices);
        self::assertSame($invoices[1]->id, $newInvoice->id);
    }

    #[Test]
    public function getResultByOriginalInvoice(): void
    {
        $customer = CustomerFactory::new()->createOne();
        $product = new ProductFactory()->nlDomain()->createOne();
        $invoice = new InvoiceFactory()
            ->for($customer)
            ->for($product)
            ->createOne();

        $bulkResult = new BatchCreateFromExistingResult([
            new CreateInvoiceFromResult(
                new InvoiceFactory()
                    ->for($customer)
                    ->for($product)
                    ->createOne(),
                new InvoiceFactory()
                    ->for($customer)
                    ->for($product)
                    ->createOne(),
            ),
            new CreateInvoiceFromResult(
                $invoice,
                new InvoiceFactory()
                    ->for($customer)
                    ->for($product)
                    ->createOne(),
            ),
            new CreateInvoiceFromResult(
                new InvoiceFactory()
                    ->for($customer)
                    ->for($product)
                    ->createOne(),
                new InvoiceFactory()
                    ->for($customer)
                    ->for($product)
                    ->createOne(),
            ),
        ]);

        $result = $bulkResult->getResultByOriginalInvoice($invoice);
        self::assertInstanceOf(CreateInvoiceFromResult::class, $result);
        $resultOriginalInvoice = $result->getOriginalInvoice();
        self::assertSame($invoice->id, $resultOriginalInvoice->id);
    }
}
