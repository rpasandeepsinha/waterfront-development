<?php

declare(strict_types=1);

namespace Waterfront\Domain\Customers\Exceptions;

use Exception;
use Throwable;

class InvalidAddressTypeException extends Exception
{
    public function __construct(int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct(
            'Invalid Address Type.',
            $code,
            $previous,
        );
    }
}
