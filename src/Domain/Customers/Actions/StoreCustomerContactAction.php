<?php

declare(strict_types=1);

namespace Waterfront\Domain\Customers\Actions;

use Waterfront\Domain\Customers\Enums\CustomerContactType;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Models\CustomerContact;

class StoreCustomerContactAction
{
    public function execute(
        Customer $customer,
        CustomerContactType $type,
        string $email,
        ?string $firstName,
        ?string $lastName,
        ?string $company,
    ): CustomerContact {
        $existingContact = $customer->customerContacts()->where('type', $type->value)->where('email', $email)->first();

        if ($existingContact instanceof CustomerContact) {
            $customerContact = $existingContact;
        } else {
            $customerContact = new CustomerContact();
            $customerContact->customer_id = $customer->id;
            $customerContact->type = $type->value;
            $customerContact->email = $email;
        }

        $customerContact->first_name = $firstName ?? $customer->first_name;
        $customerContact->last_name = $lastName ?? $customer->last_name;
        $customerContact->company = $company;
        $customerContact->save();

        return $customerContact;
    }
}
