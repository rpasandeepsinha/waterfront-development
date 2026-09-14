<?php

declare(strict_types=1);

namespace Waterfront\Domain\Customers\Exceptions;

use Exception;
use Throwable;

class StoreCustomerAddressNoExistingCustomerException extends Exception
{
    /**
     * @param array<string, string|null> $address
     */
    public function __construct(array $address, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct(
            sprintf(
                'No customer found when creating address: %s',
                json_encode($address, JSON_THROW_ON_ERROR),
            ),
            $code,
            $previous,
        );
    }
}
