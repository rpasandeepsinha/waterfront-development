<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Waterfront\Domain\Customers\Models\MigratedCustomer;

class MigrationCustomerRepository
{
    public function isCustomerInActiveMigrationWithInvoicingDisabled(int $customerId): bool
    {
        return MigratedCustomer::query()
            ->whereHas('customers', function (Builder $qb) use ($customerId): void {
                $qb->where('id', $customerId);
            })
            ->where('enable_invoicing', false)
            ->exists();
    }

    public function findMigratedCustomerByCustomerNumberAndBU(string $customerNumber, string $buName): ?MigratedCustomer
    {
        return MigratedCustomer::query()
            ->where('reference_customer_number', $customerNumber)
            ->where('reference_name', 'ilike', $buName)
            ->first();
    }
}
