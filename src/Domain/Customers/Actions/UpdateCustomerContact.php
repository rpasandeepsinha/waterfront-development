<?php

declare(strict_types=1);

namespace Waterfront\Domain\Customers\Actions;

use Waterfront\Domain\Customers\DTO\ContactDTO;
use Waterfront\Domain\Customers\Exceptions\UpdateCustomerContactException;
use Waterfront\Domain\Customers\Models\CustomerContact;

class UpdateCustomerContact
{
    /**
     * @throws UpdateCustomerContactException
     */
    public function execute(CustomerContact $customerContact, ContactDTO $contactDTO): void
    {
        $customerContact->first_name = $contactDTO->firstName;
        $customerContact->last_name = $contactDTO->lastName;
        $customerContact->type = $contactDTO->type->value;
        $customerContact->company = $contactDTO->company;
        $customerContact->email = $contactDTO->email;
        $customerContact->save();
    }
}
