<?php

declare(strict_types=1);

namespace Waterfront\Domain\Harbor\DTO\Services\Invoice\InvoiceCrediter;

use Waterfront\Domain\Invoices\Models\Invoice;

class InvoiceCreditResult
{
    public function __construct(
        private readonly Invoice $originalInvoice,
        private readonly Invoice $creditInvoice,
    ) {
    }

    public function getOriginalInvoice(): Invoice
    {
        return $this->originalInvoice;
    }

    public function getCreditInvoice(): Invoice
    {
        return $this->creditInvoice;
    }
}
