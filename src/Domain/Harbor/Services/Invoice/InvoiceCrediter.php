<?php

declare(strict_types=1);

namespace Waterfront\Domain\Harbor\Services\Invoice;

use Waterfront\Domain\Harbor\DTO\Services\Invoice\InvoiceCrediter\InvoiceCreditResult;
use Waterfront\Domain\Harbor\DTO\Services\Invoice\InvoiceCrediter\InvoiceToCredit;
use Waterfront\Domain\Invoices\Models\Invoice;

class InvoiceCrediter
{
    /**
     * Creates a credit Invoice from the given debit Invoice.
     *
     * TODO: Also create a new Invoice based on $shouldCreateNewInvoice in InvoiceToCredit.
     *  https://yh-jira.atlassian.net/browse/WATER-3792
     */
    public function credit(InvoiceToCredit $invoiceToCredit): InvoiceCreditResult
    {
        /** @var Invoice $creditInvoice */
        $creditInvoice = Invoice::query()->create([
            ...$this->getCreditInvoiceDataFromExisting($invoiceToCredit->getInvoice()),
            'parent_invoice_id' => $invoiceToCredit->getInvoice()->id,
            'paid' => false,
            'prepaid_reference' => null,
            'start_date' => $invoiceToCredit->getCreditStartDate(),
            'credit_reason' => $invoiceToCredit->getCreditReason(),

            // Inverted
            'net_price' => -$invoiceToCredit->getAmountToCredit(),
            'gross_price' => -$invoiceToCredit->getAmountToCredit(),
        ]);

        return new InvoiceCreditResult(
            $invoiceToCredit->getInvoice(),
            $creditInvoice,
        );
    }

    /**
     * Completely clones the given Invoice entity's data,
     * except for the price, which is inverted to be credit.
     *
     * @return array<string, mixed>
     */
    private function getCreditInvoiceDataFromExisting(Invoice $invoice): array
    {
        $newMergeWithInvoiceId = null;
        if ($invoice->mergeOnPdfWithInvoice !== null) {
            $newMergeWithInvoiceId = $invoice->mergeOnPdfWithInvoice->childInvoices()->latest()->first()?->id;
        }

        return [
            'subscription_id' => $invoice->subscription_id,
            'customer_id' => $invoice->customer_id,
            'product_id' => $invoice->product_id,
            'start_date' => $invoice->start_date,
            'end_date' => $invoice->end_date,
            'period' => $invoice->period,
            'vat_code' => $invoice->vat_code,
            'vat_rate' => $invoice->vat_rate,
            'ledger_code' => $invoice->ledger_code,
            'merge_on_pdf_with_invoice_id' => $newMergeWithInvoiceId,
            'description' => $invoice->description,
            'title' => $invoice->title,
            'group_label' => $invoice->group_label,
            'type' => $invoice->type,
            'prepaid_reference' => $invoice->prepaid_reference,
        ];
    }
}
