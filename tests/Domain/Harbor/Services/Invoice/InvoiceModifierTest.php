<?php

declare(strict_types=1);

namespace Tests\Domain\Harbor\Services\Invoice;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\InvoiceFactory;
use Tests\Factories\ProductFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Harbor\Services\Invoice\InvoiceModifier;

#[CoversClass(InvoiceModifier::class)]
class InvoiceModifierTest extends IntegrationTestCase
{
    private InvoiceModifier $invoiceModifier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->invoiceModifier = self::resolve(InvoiceModifier::class);
    }

    #[Test]
    public function modifiesGivenInvoices(): void
    {
        $product = new ProductFactory()->nlDomain()->createOne();
        $invoices = [
            new InvoiceFactory()->withCustomer()->for($product)->createOne(),
            new InvoiceFactory()->withCustomer()->for($product)->createOne(),
            new InvoiceFactory()->withCustomer()->for($product)->createOne(),
        ];

        $sentToHarborAt = CarbonImmutable::now();

        $this->invoiceModifier->bulkUpdate($invoices, [
            'sent_to_harbor_at' => $sentToHarborAt,
        ]);

        foreach ($invoices as $invoice) {
            $realSentAt = $invoice->sent_to_harbor_at;

            self::assertNotNull($realSentAt);
            self::assertSame($sentToHarborAt->timestamp, $realSentAt->timestamp);
        }
    }
}
