<?php

declare(strict_types=1);

namespace Waterfront\Domain\Customers\Actions;

use Waterfront\Apps\API\Compass\DTO\CustomerUpdateDTO;
use Waterfront\Domain\Customers\Models\CustomerAddress;

class UpdateCustomerAction
{
    public function execute(CustomerUpdateDTO $updateDTO): void
    {
        $customer = $updateDTO->customer;
        $customer->first_name = $updateDTO->firstName;
        $customer->last_name = $updateDTO->lastName;
        $customer->email = $updateDTO->email;
        $customer->gender = $updateDTO->gender->value;
        $customer->payment_type = $updateDTO->paymentType;
        $customer->organization = $updateDTO->organization;
        $customer->department = $updateDTO->department;
        $customer->coc_number = $updateDTO->cocNumber;
        $customer->vat_number = $updateDTO->vatNumber;
        $customer->terms_of_payment = $updateDTO->paymentTerm;
        $customer->credit_limit = $updateDTO->creditLimit;

        $customer->save();

        $address = $customer->address?->first();

        if ($address === null) {
            $address = new CustomerAddress();
            $address->customer_id = $customer->id;
        }

        $address->street_name = $updateDTO->street_name;
        $address->street_number = $updateDTO->street_number;
        $address->city = $updateDTO->city;
        $address->street_number_addition = $updateDTO->street_number_addition;
        $address->zip_code = $updateDTO->zip_code;
        $address->country_code = $updateDTO->countryCode;
        $address->save();
    }
}
