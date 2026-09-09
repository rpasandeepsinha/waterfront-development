<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Exceptions;

use Exception;

class DomainBusinessUnitNotFoundException extends Exception
{
    public function __construct(string $businessUnitSlug, int $code = 0, ?Exception $previous = null)
    {
        parent::__construct(
            sprintf(
                'The given business unit slug [%s] could not be found. Please ensure that the business unit exists and is correctly configured.',
                $businessUnitSlug
            ),
            $code,
            $previous
        );
    }
}
