<?php

declare(strict_types=1);

namespace Waterfront\Infra\Authentication\DTO;

use SandwaveIo\LighthouseAuthBase\Identity\KratosIdentity;
use Waterfront\Domain\Customers\Models\Customer;

class AuthenticatedCustomer
{
    public function __construct(
        public readonly Customer $customer,
        public readonly KratosIdentity $identitySchema,
        public readonly bool $verified,
    ) {
    }
}
