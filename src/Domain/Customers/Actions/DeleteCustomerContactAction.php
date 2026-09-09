<?php

declare(strict_types=1);

namespace Waterfront\Domain\Customers\Actions;

use Waterfront\Domain\Customers\Exceptions\DeleteCustomerContactException;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Models\CustomerContact;

class DeleteCustomerContactAction
{
    /**
     * @throws DeleteCustomerContactException
     */
    public function execute(Customer $customer, CustomerContact $customerContact): void
    {
        if ($customer->id !== $customerContact->customer_id) {
            // Route model binding also uses the relation to retrieve the customer contact,
            // so this should only happen if we call the action ourselves
            throw DeleteCustomerContactException::customerIdDoesNotMatch($customer, $customerContact);
        }

        $customerContact->delete();
    }
}
