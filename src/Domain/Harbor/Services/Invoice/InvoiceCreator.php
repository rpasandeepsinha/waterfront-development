<?php

declare(strict_types=1);

namespace Waterfront\Domain\Harbor\Services\Invoice;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Waterfront\Domain\Customers\Exceptions\InvalidCountryCodeException;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Services\CustomerVatService;
use Waterfront\Domain\Harbor\DTO\Services\Invoice\InvoiceCreator\BatchCreateFromExistingResult;
use Waterfront\Domain\Harbor\DTO\Services\Invoice\InvoiceCreator\CreateInvoiceFromResult;
use Waterfront\Domain\Invoices\Models\Invoice;

class InvoiceCreator
{
    public function __construct(
        private readonly CustomerVatService $vatService,
    ) {
    }

    /**
     * Used to transfer invoice line data to a new one that will have potentially updated customer.
     * Does not modify the passed Invoice entries.
     *
     * @param Invoice[] $invoices The Invoices to create new ones from.
     *
     * @throws InvalidCountryCodeException
     */
    public function batchCreateFromExisting(array $invoices): BatchCreateFromExistingResult
    {
        /** @var int[] $newInvoiceIds */
        $newInvoiceIds = [];
        $result = new BatchCreateFromExistingResult(
            array_map(function (Invoice $originalInvoice) use (&$newInvoiceIds): CreateInvoiceFromResult {
                $createInvoiceFromResult = $this->createInvoiceFrom($originalInvoice);
                $newInvoiceIds[] = $createInvoiceFromResult->getNewInvoice()->id;

                return $createInvoiceFromResult;
            }, $invoices),
        );

        Log::info(sprintf(
            'Completed batch creation action, resulting in these new Invoice IDs: [%s].',
            implode(', ', $newInvoiceIds),
        ));

        return $result;
    }

    /**
     * Does pretty much the same thing as InvoiceRepository->createInvoice(...),
     * but the difference here is that we use an existing invoice line as reference.
     *
     * @param Invoice $invoiceLine The invoice line to base the new one off of.
     *
     * @throws ModelNotFoundException
     * @throws InvalidCountryCodeException
     */
    private function createInvoiceFrom(Invoice $invoiceLine): CreateInvoiceFromResult
    {
        /** @var Customer $endCustomer */
        $endCustomer = Customer::query()->findOrFail($invoiceLine->customer_id);

        $customer = $invoiceLine->customer;

        $customerVatDTO = $this->vatService->getCustomerVatData($customer);

        /** @var Invoice $createdInvoice */
        $createdInvoice = Invoice::query()->create([
            'subscription_id' => $invoiceLine->subscription_id,
            'customer_id' => $endCustomer->id,
            'product_id' => $invoiceLine->product_id,
            'start_date' => $invoiceLine->start_date,
            'end_date' => $invoiceLine->end_date,
            'period' => $invoiceLine->period,
            'gross_price' => $invoiceLine->gross_price,
            'net_price' => $invoiceLine->net_price,
            'vat_code' => $customerVatDTO->vatCode,
            'vat_rate' => $customerVatDTO->vatRate,
            'ledger_code' => $invoiceLine->ledger_code,
            'paid' => false,
            'title' => $invoiceLine->title,
            'description' => $invoiceLine->description,
            'group_label' => $invoiceLine->group_label,
            'type' => $invoiceLine->type,
            'prepaid_reference' => null,
        ]);

        return new CreateInvoiceFromResult($invoiceLine, $createdInvoice);
    }
}
