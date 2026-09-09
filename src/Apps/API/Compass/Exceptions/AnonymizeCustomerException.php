<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Exceptions;

use Exception;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Lighthouse\Exceptions\DetachCustomerNumberFromIdentityFailedException;

class AnonymizeCustomerException extends Exception
{
    public static function alreadyAnonymized(Customer $customer): self
    {
        return new self(sprintf(
            'Customer %d is already anonymized',
            $customer->customer_number
        ));
    }

    public static function stillHasActiveSubscriptions(Customer $customer): self
    {
        return new self(sprintf(
            'Customer %d still has active subscriptions',
            $customer->customer_number
        ));
    }

    public static function stillHasCancelledSubscriptions(Customer $customer): self
    {
        return new self(sprintf(
            'Customer %d still has cancelled subscriptions',
            $customer->customer_number
        ));
    }

    public static function stillHasOpenOrders(Customer $customer): self
    {
        return new self(sprintf(
            'Customer %d still has open orders',
            $customer->customer_number
        ));
    }

    public static function stillHasUnpaidInvoiceAmount(Customer $customer): self
    {
        return new self(sprintf(
            'Customer %d still has unpaid invoices',
            $customer->customer_number
        ));
    }

    public static function failedToDetachCustomerNumberFromIdentity(
        int $customerNumber,
        string $identifier,
        DetachCustomerNumberFromIdentityFailedException $exception
    ): self {
        return new self(
            sprintf(
                'Failed to detach customer number %d from lighthouse identity %s',
                $customerNumber,
                $identifier
            ),
            $exception->getCode(),
            $exception
        );
    }
}
