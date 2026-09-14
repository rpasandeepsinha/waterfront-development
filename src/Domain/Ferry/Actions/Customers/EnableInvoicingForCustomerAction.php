<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Actions\Customers;

use Psr\Log\LoggerInterface;
use Waterfront\Domain\Customers\Actions\DispatchInvoicingForCustomerAction;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Support\Enums\LoggingContextKeys;

class EnableInvoicingForCustomerAction
{
    public function __construct(
        private readonly DispatchInvoicingForCustomerAction $dispatchInvoicingForCustomerAction,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function execute(Customer $customer): void
    {
        $customer
            ->migratedCustomers()
            ->update([
                'enable_invoicing' => true,
                'successful' => true,
            ]);

        $migratedCustomer = $customer->migratedCustomers->firstOrFail();

        $this->logger->info(
            'Enabled invoicing for migrated customer',
            [
                LoggingContextKeys::CUSTOMER_ID => $customer->id,
                LoggingContextKeys::CUSTOMER_NUMBER => $customer->customer_number,
                LoggingContextKeys::MIGRATION_REFERENCE_NAME => $migratedCustomer->reference_name,
                LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID => $migratedCustomer->reference_customer_number,
                LoggingContextKeys::META => [
                    'migrated_customer_id' => $migratedCustomer->id,
                ],
            ],
        );

        $this->dispatchInvoicingForCustomerAction->execute($customer);
    }
}
