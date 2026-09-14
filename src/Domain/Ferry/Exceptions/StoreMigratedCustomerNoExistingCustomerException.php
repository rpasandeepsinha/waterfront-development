<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Exceptions;

use Exception;
use Throwable;

class StoreMigratedCustomerNoExistingCustomerException extends Exception
{
    public function __construct(
        string $customerName,
        string $buCustomerNumber,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            sprintf(
                'Customer %s does not exist in database when creating migrated customer reference for old customer reference id %s',
                $customerName,
                $buCustomerNumber,
            ),
            $code,
            $previous,
        );
    }
}
