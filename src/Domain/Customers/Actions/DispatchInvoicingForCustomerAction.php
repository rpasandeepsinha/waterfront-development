<?php

declare(strict_types=1);

namespace Waterfront\Domain\Customers\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Psr\Log\LoggerInterface;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Invoices\Events\InvoiceCreatedEvent;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Support\Enums\LoggingContextKeys;

class DispatchInvoicingForCustomerAction
{
    public function __construct(
        private readonly Dispatcher $eventDispatcher,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function execute(Customer $customer): void
    {
        $customerInvoices = $customer->invoices()->whereNull('sent_to_harbor_at')->get();

        if ($customerInvoices->count() === 0) {
            $this->logger->info("Customer `{$customer->id}` does not have invoices.");

            return;
        }

        $migratedCustomer = $customer->migratedCustomers->first();

        /** @var Invoice $customerInvoice */
        foreach ($customerInvoices as $customerInvoice) {
            $this->logger->info(
                'Dispatching invoice created event',
                [
                    LoggingContextKeys::CUSTOMER_ID => $customer->id,
                    LoggingContextKeys::CUSTOMER_NUMBER => $customer->customer_number,
                    LoggingContextKeys::INVOICE_LINE_ID => $customerInvoice->id,
                    LoggingContextKeys::MIGRATION_REFERENCE_NAME => $migratedCustomer?->reference_name,
                    LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID =>
                        $migratedCustomer?->reference_customer_number,
                ],
            );

            $this->eventDispatcher->dispatch(new InvoiceCreatedEvent($customerInvoice, false));
        }
    }
}
