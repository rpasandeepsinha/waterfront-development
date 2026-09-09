<?php

declare(strict_types=1);

namespace Waterfront\Domain\Harbor\DTO\Services\Invoice\InvoiceCreator;

use Waterfront\Domain\Invoices\Models\Invoice;

class BatchCreateFromExistingResult
{
    /**
     * @param CreateInvoiceFromResult[] $results
     */
    public function __construct(
        private readonly array $results,
    ) {
    }

    /**
     * @return CreateInvoiceFromResult[]
     */
    public function getResults(): array
    {
        return $this->results;
    }

    /**
     * @return Invoice[]
     */
    public function getNewInvoices(): array
    {
        return array_reduce($this->getResults(), function (array $newInvoices, CreateInvoiceFromResult $result) {
            $newInvoices[] = $result->getNewInvoice();

            return $newInvoices;
        }, []);
    }

    public function getResultByOriginalInvoice(Invoice $originalInvoice): ?CreateInvoiceFromResult
    {
        $matches = array_filter(
            $this->getResults(),
            fn (CreateInvoiceFromResult $result): bool => $result->getOriginalInvoice()->id === $originalInvoice->id
        );
        $foundResult = reset($matches);

        return $foundResult instanceof CreateInvoiceFromResult ? $foundResult : null;
    }
}
