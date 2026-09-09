<?php

declare(strict_types=1);

namespace Waterfront\Domain\Invoices\Services;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Collection;
use Psr\Log\LoggerInterface;
use Waterfront\Domain\Ferry\Repositories\MigrationCustomerRepository;
use Waterfront\Domain\Invoices\Jobs\DispatchConsolidatedInvoicesForCustomer;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Support\Enums\LoggingContextKeys;

readonly class InvoiceToHarborDispatcher
{
    public function __construct(
        private LoggerInterface $logger,
        private Dispatcher $bus,
        private MigrationCustomerRepository $migrationCustomerRepository,
    ) {
    }

    /**
     * @param Collection<int, Invoice> $models
     */
    public function dispatch(Collection $models, bool $createInvoiceInstantly = true): void
    {
        $models->ensure(Invoice::class);

        /* The collection can be multi customer, but we want to create
         * and dispatch per customer. So we regroup the given collection
         * into separate collections for each customer.
         */
        /** @var Collection<string,Collection<int, Invoice>> $invoicesPerCustomerAndPrepaidReference */
        $invoicesPerCustomerAndPrepaidReference = $models->groupBy(
            fn (Invoice $invoice) => $invoice->customer_id . '-' . ($invoice->prepaid_reference ?? '')
        );

        foreach ($invoicesPerCustomerAndPrepaidReference as $customerInvoices) {
            $customerInvoices->ensure(Invoice::class);
            $customer = $customerInvoices->firstOrFail()->customer;

            if ($customer->anonymized_at !== null) {
                $this->logger->info(
                    sprintf('Dispatch invoices is skipped for an anonymized customer: %s', $customer->customer_number),
                    [LoggingContextKeys::CUSTOMER_NUMBER => $customer->customer_number]
                );
                continue;
            }

            if ($this->migrationCustomerRepository->isCustomerInActiveMigrationWithInvoicingDisabled($customer->id)) {
                $this->logger->info(
                    sprintf(
                        'Dispatch invoices is skipped, customer is in migration and invoice is disabled: %s',
                        $customer->customer_number
                    ),
                    [LoggingContextKeys::CUSTOMER_NUMBER => $customer->customer_number]
                );
                continue;
            }

            $notSentInvoices = $customerInvoices->filter(
                fn (Invoice $invoice) => $invoice->sent_to_harbor_at === null
            );

            if ($notSentInvoices->isNotEmpty()) {
                $this->logger->info(
                    sprintf('Dispatch invoices for customer %s', $customer->customer_number),
                    [
                        LoggingContextKeys::CUSTOMER_NUMBER => $customer->customer_number,
                    ],
                );

                $this->bus->dispatch(
                    new DispatchConsolidatedInvoicesForCustomer(
                        customer: $customer,
                        invoices: $notSentInvoices->all(),
                        createInvoiceInstantly: $createInvoiceInstantly,
                    )
                );
            }
        }
    }
}
