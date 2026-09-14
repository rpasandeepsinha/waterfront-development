<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Exceptions;

use Exception;
use Throwable;

class FailedToFetchNameserversException extends Exception
{
    public function __construct(string $domain, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct(
            sprintf(
                'Failed to fetch nameservers for domain: %s',
                $domain,
            ),
            $code,
            $previous,
        );
    }
}
