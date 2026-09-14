<?php

declare(strict_types=1);

namespace Waterfront\Domain\Customers\Exceptions;

use Exception;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Models\CustomerContact;

class DeleteCustomerContactException extends Exception
{
    public static function customerIdDoesNotMatch(Customer $customer, CustomerContact $customerContact): self
    {
        return new self(
            sprintf(
                'Tried to delete customer contact %d but customer id %d does not match customer id %d of contact',
                $customerContact->id,
                $customer->id,
                $customerContact->customer_id,
            ),
        );
    }
}
