<?php

declare(strict_types=1);

namespace Waterfront\Domain\Customers\Actions;

use Waterfront\Domain\Customers\Exceptions\StoreCustomerAddressNoExistingCustomerException;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Models\CustomerAddress;
use Waterfront\Domain\Customers\Repositories\CustomerAddressRepository;

class StoreCustomerAddressAction
{
    public function __construct(
        private readonly CustomerAddressRepository $customerAddressRepository,
    ) {
    }

    /**
     * @throws StoreCustomerAddressNoExistingCustomerException
     */
    public function execute(
        Customer $customer,
        string $streetName,
        string $streetNumber,
        string|null $streetNumberAddition,
        string $zipCode,
        string $city,
        string $countryCode,
        string|null $type = null,
    ): CustomerAddress {
        if ($customer->id === null) {
            throw new StoreCustomerAddressNoExistingCustomerException([
                'streetName' => $streetName,
                'streetNumber' => $streetNumber,
                'streetNumberAddition' => $streetNumberAddition,
                'zipCode' => $zipCode,
                'city' => $city,
                'country' => $countryCode,
            ]);
        }

        $existingAddress = $this->customerAddressRepository->findByCustomerAddressDetails(
            customer: $customer,
            city: $city,
            zipCode: $zipCode,
            streetName: $streetName,
            streetNumber: $streetNumber,
            streetNumberAddition: $streetNumberAddition,
        );

        if ($existingAddress instanceof CustomerAddress) {
            return $existingAddress;
        }

        $address = new CustomerAddress();
        $address->customer_id = $customer->id;
        $address->street_name = $streetName;
        $address->street_number = $streetNumber;
        $address->street_number_addition = $streetNumberAddition;
        $address->zip_code = $zipCode;
        $address->city = $city;
        $address->country_code = $countryCode;
        $address->type = $type;

        $address->save();

        return $address;
    }
}
