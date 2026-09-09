<?php

declare(strict_types=1);

namespace Waterfront\Domain\Harbor\DTO\Services\Invoice\InvoiceCreator;

use Waterfront\Domain\Invoices\Models\Invoice;

class CreateInvoiceFromResult
{
    public function __construct(
        private readonly Invoice $originalInvoice,
        private readonly Invoice $newInvoice,
    ) {
    }

    public function getOriginalInvoice(): Invoice
    {
        return $this->originalInvoice;
    }

    public function getNewInvoice(): Invoice
    {
        return $this->newInvoice;
    }
}
