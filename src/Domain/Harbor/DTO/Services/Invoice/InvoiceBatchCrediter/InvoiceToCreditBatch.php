<?php

declare(strict_types=1);

namespace Waterfront\Domain\Harbor\DTO\Services\Invoice\InvoiceBatchCrediter;

use Waterfront\Domain\Harbor\DTO\Services\Invoice\InvoiceCrediter\InvoiceToCredit;

class InvoiceToCreditBatch
{
    /**
     * @param InvoiceToCredit[] $invoicesToCredit
     */
    public function __construct(
        private array $invoicesToCredit = [],
    ) {
    }

    public function add(InvoiceToCredit $invoiceToCredit): void
    {
        $this->invoicesToCredit[] = $invoiceToCredit;
    }

    /**
     * @return InvoiceToCredit[]
     */
    public function getInvoicesToCredit(): array
    {
        return $this->invoicesToCredit;
    }

    /**
     * Retrieves InvoiceToCredit[] where $shouldCreateNewInvoice is true.
     *
     * @return InvoiceToCredit[]
     */
    public function getInvoicesForNew(): array
    {
        return array_filter(
            $this->getInvoicesToCredit(),
            fn (InvoiceToCredit $invoiceToCredit): bool => $invoiceToCredit->shouldCreateNewInvoice(),
        );
    }

    public function count(): int
    {
        return count($this->getInvoicesToCredit());
    }
}
