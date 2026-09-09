<?php

declare(strict_types=1);

namespace Waterfront\Domain\Customers\Actions;

use Waterfront\Domain\Customers\Models\Customer;

class MarkMigratedCustomersAdministrativeSuccessfulAction
{
    public function execute(Customer $customer): void
    {
        // We're assuming that if you want to enable invoicing it is also administratively successful
        $customer->migratedCustomers()
            ->where('administrative_successful', false)
            ->update(['administrative_successful' => true]);
    }
}
