<?php

declare(strict_types=1);

namespace Waterfront\Domain\Customers\Actions;

use Waterfront\Domain\Customers\Models\Customer;

class SetCustomerVerifiedAction
{
    public function execute(Customer $customer): void
    {
        $customer->is_verified = true;
        $customer->save();
    }
}
