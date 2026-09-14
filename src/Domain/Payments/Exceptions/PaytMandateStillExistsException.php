<?php

declare(strict_types=1);

namespace Waterfront\Domain\Payments\Exceptions;

use Exception;
use Throwable;
use Waterfront\Domain\Payments\Models\Mandate;

class PaytMandateStillExistsException extends Exception
{
    public function __construct(Mandate $mandate, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct(
            sprintf(
                '[Mandate %d] Payt mandate ID %s still exists for mollie mandate %s and mollie customer %s',
                $mandate->id,
                $mandate->payt_mandate_reference_id,
                $mandate->mollie_mandate_reference_id,
                $mandate->mollieCustomer->mollie_customer_reference_id,
            ),
            $code,
            $previous,
        );
    }
}
