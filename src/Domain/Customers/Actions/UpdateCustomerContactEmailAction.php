<?php

declare(strict_types=1);

namespace Waterfront\Domain\Customers\Actions;

use Waterfront\Domain\Customers\Exceptions\UpdateCustomerContactException;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Models\CustomerContact;

class UpdateCustomerContactEmailAction
{
    /**
     * @throws UpdateCustomerContactException
     */
    public function execute(Customer $customer, CustomerContact $customerContact, string $email): void
    {
        if ($customer->id !== $customerContact->customer_id) {
            // Route model binding also uses the relation to retrieve the customer contact,
            // so this should only happen if we call the action ourselves
            throw UpdateCustomerContactException::customerIdDoesNotMatch($customer, $customerContact);
        }

        $customerContact->email = $email;
        $customerContact->save();
    }
}
