<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Models\MigratedCustomer;

class MigratedCustomersRepository
{
    /**
     * @return Collection<int, MigratedCustomer>
     */
    public function getMigratedCustomersByGroupType(string $groupType): Collection
    {
        return MigratedCustomer::query()->where('group_type', $groupType)->get();
    }

    public function isMigratedCustomerEligibleForMailing(string $emailAddress): bool
    {
        return Customer::query()
            ->where(
                'email',
                $emailAddress,
            )
            ->whereHas('migratedCustomers', function (Builder $builder): void {
                $builder->where('successful', false);
            })
            ->doesntExist();
    }

    public function getFirstCustomerByMigratedCustomerReferenceId(string $referenceId): Customer
    {
        return $this->getMigratedCustomerByReferenceId($referenceId)->customers->firstOrFail();
    }

    private function getMigratedCustomerByReferenceId(string $referenceId): MigratedCustomer
    {
        return MigratedCustomer::where('reference_customer_number', $referenceId)->firstOrFail();
    }
}
