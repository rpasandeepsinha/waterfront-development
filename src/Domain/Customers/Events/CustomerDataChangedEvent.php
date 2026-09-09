<?php

declare(strict_types=1);

namespace Waterfront\Domain\Customers\Events;

use Waterfront\Domain\Customers\Models\Customer;

class CustomerDataChangedEvent
{
    public function __construct(
        public Customer $customer
    ) {
    }
}
