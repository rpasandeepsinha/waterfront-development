<?php

declare(strict_types=1);

namespace Waterfront\Infra\PaytClient\Exceptions;

use Exception;
use Throwable;

class PaytMandateIdNotNumericException extends Exception
{
    public function __construct(string $paytMandateId, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct(
            sprintf(
                'Payt mandate ID is not numeric: %s',
                $paytMandateId,
            ),
            $code,
            $previous
        );
    }
}
