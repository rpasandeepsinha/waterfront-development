<?php

declare(strict_types=1);

namespace Waterfront\Domain\Harbor\DTO\Services\Invoice\InvoiceBatchCrediter;

use Waterfront\Domain\Invoices\Models\Invoice;

class BatchInvoiceCreditResult
{
    /**
     * @param Invoice[] $creditInvoices
     * @param Invoice[] $newInvoices
     */
    public function __construct(
        private readonly array $creditInvoices,
        private readonly array $newInvoices,
    ) {
    }

    /**
     * @return Invoice[]
     */
    public function getCreditInvoices(): array
    {
        return $this->creditInvoices;
    }

    /**
     * @return Invoice[]
     */
    public function getNewInvoices(): array
    {
        return $this->newInvoices;
    }
}
