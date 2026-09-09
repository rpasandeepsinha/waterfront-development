<?php

declare(strict_types=1);

namespace Waterfront\Domain\Harbor\Services\Invoice;

use Waterfront\Domain\Customers\Exceptions\InvalidCountryCodeException;
use Waterfront\Domain\Harbor\DTO\Services\Invoice\InvoiceBatchCrediter\BatchInvoiceCreditResult;
use Waterfront\Domain\Harbor\DTO\Services\Invoice\InvoiceBatchCrediter\InvoiceToCreditBatch;
use Waterfront\Domain\Harbor\DTO\Services\Invoice\InvoiceCrediter\InvoiceCreditResult;
use Waterfront\Domain\Harbor\DTO\Services\Invoice\InvoiceCrediter\InvoiceToCredit;
use Waterfront\Domain\Invoices\Models\Invoice;

/**
 * Allows for crediting an entire set of Invoice entities.
 *
 * TODO: This service is bloat.
 *  https://yh-jira.atlassian.net/browse/WATER-3792.
 */
class InvoiceBatchCrediter
{
    public function __construct(
        private readonly InvoiceCrediter $invoiceCrediter,
        private readonly InvoiceCreator $invoiceCreator,
    ) {
    }

    /**
     * @throws InvalidCountryCodeException
     */
    public function batchCredit(InvoiceToCreditBatch $invoiceToCreditBatch): BatchInvoiceCreditResult
    {
        /** @var Invoice[] $creditInvoices */
        $creditInvoices = [];
        array_map(
            function (InvoiceToCredit $invoiceToCredit) use (&$creditInvoices): InvoiceCreditResult {
                $creditResult = $this->invoiceCrediter->credit($invoiceToCredit);

                $creditInvoices[] = $creditResult->getCreditInvoice();

                return $creditResult;
            },
            $invoiceToCreditBatch->getInvoicesToCredit(),
        );
        $batchCreateResult = $this->invoiceCreator->batchCreateFromExisting(array_map(
            fn (InvoiceToCredit $invoiceToCredit): Invoice => $invoiceToCredit->getInvoice(),
            $invoiceToCreditBatch->getInvoicesForNew(),
        ));
        $newInvoices = $batchCreateResult->getNewInvoices();

        return new BatchInvoiceCreditResult($creditInvoices, $newInvoices);
    }
}
