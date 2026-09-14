<?php

declare(strict_types=1);

namespace Waterfront\Domain\Payments\Repositories;

use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Payments\Models\Mandate;

readonly class PaymentMandateRepository
{
    public function getActivePaymentMandateForCustomer(Customer $customer): ?Mandate
    {
        if ($customer->mollieCustomer === null) {
            return null;
        }

        /* There should not be more than one active mandate per payment method,
         * and right now we only support the direct debit method. So simply
         * assume the most recent mandate to be the right one. */
        /** @var Mandate $mandate */
        $mandate = $customer->mollieCustomer->mandates->last();

        return $mandate;
    }
}
