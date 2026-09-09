<?php

declare(strict_types=1);

namespace Waterfront\Domain\Customers\Repositories;

use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Models\CustomerAddress;

class CustomerAddressRepository
{
    public function findByCustomerAddressDetails(
        Customer $customer,
        string $city,
        string $zipCode,
        string $streetName,
        string $streetNumber,
        string|null $streetNumberAddition,
    ): ?CustomerAddress {
        return CustomerAddress::query()
            ->where(
                [
                    'customer_id' => $customer->id,
                    'city' => $city,
                    'zip_code' => $zipCode,
                    'street_name' => $streetName,
                    'street_number' => $streetNumber,
                    'street_number_addition' => $streetNumberAddition,
                ]
            )->first();
    }
}
