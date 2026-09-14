<?php

declare(strict_types=1);

namespace Waterfront\Domain\Customers\Exceptions;

use Exception;

class InvalidCountryCodeException extends Exception
{
    public function __construct(string $countryCode, int $code = 0, ?Exception $exception = null)
    {
        parent::__construct(
            "Invalid country code '$countryCode'",
            $code,
            $exception,
        );
    }
}
