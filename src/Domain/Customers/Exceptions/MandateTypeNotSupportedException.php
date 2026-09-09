<?php

declare(strict_types=1);

namespace Waterfront\Domain\Customers\Exceptions;

use Exception;
use Throwable;

class MandateTypeNotSupportedException extends Exception
{
    public function __construct(string $type, ?Throwable $previous = null)
    {
        parent::__construct(
            "Mandate type '$type' not supported",
            0,
            $previous
        );
    }
}
