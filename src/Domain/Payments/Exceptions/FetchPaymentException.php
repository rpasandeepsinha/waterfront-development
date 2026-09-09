<?php

declare(strict_types=1);

namespace Waterfront\Domain\Payments\Exceptions;

use Throwable;

class FetchPaymentException extends PaymentException
{
    public function __construct(string $resultErrorMessage, int $resultErrorCode = 0, ?Throwable $previous = null)
    {
        parent::__construct(
            "Error while fetching payment: $resultErrorMessage",
            $resultErrorCode,
            $previous
        );
    }
}
