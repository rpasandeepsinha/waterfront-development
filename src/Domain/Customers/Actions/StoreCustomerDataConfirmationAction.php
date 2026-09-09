<?php

declare(strict_types=1);

namespace Waterfront\Domain\Customers\Actions;

use Carbon\CarbonImmutable;
use Waterfront\Domain\Customers\Models\Customer;

class StoreCustomerDataConfirmationAction
{
    public function execute(Customer $customer): void
    {
        $customer->data_last_confirmed_at = CarbonImmutable::now();
        $customer->save();
    }
}
