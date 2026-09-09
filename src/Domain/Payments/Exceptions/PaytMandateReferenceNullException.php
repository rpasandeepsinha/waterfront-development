<?php

declare(strict_types=1);

namespace Waterfront\Domain\Payments\Exceptions;

use Exception;
use Throwable;
use Waterfront\Domain\Payments\Models\Mandate;

class PaytMandateReferenceNullException extends Exception
{
    public function __construct(Mandate $mandate, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct(
            sprintf(
                'Payt mandate reference null for Mandate "%d"',
                $mandate->id,
            ),
            $code,
            $previous
        );
    }
}
