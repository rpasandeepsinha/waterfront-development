<?php

declare(strict_types=1);

namespace Waterfront\Domain\Customers\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Customers\Models\Customer;

class CustomerRepository
{
    public function findByCustomerNumber(int $customerNumber): ?Customer
    {
        return Customer::where('customer_number', $customerNumber)->first();
    }

    public function findByCustomerNumberWithMigratedCustomer(int $customerNumber): ?Customer
    {
        return Customer::where('customer_number', $customerNumber)->with('migratedCustomers')->first();
    }

    /**
     * @return Collection<int, Customer>
     */
    public function findByMigratedCustomerReference(string $reference): Collection
    {
        return Customer::whereHas(
            'migratedCustomers',
            static fn (Builder $builder) => $builder->where('reference_customer_number', 'ilike', "%$reference%"),
        )->get();
    }

    public function findByUuid(UuidInterface $uuid): ?Customer
    {
        return Customer::where('uuid', $uuid->toString())->first();
    }

    public function getById(int $id): Customer
    {
        return Customer::where('id', $id)->firstOrFail();
    }
}
