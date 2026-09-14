<?php

declare(strict_types=1);

namespace Waterfront\Infra\OpenproviderClient\Exceptions;

use RuntimeException;
use Throwable;

class OpenProviderResultException extends RuntimeException
{
    public function __construct(string $description, int $statusCode, ?Throwable $previous = null)
    {
        parent::__construct(
            'We got an error response from OpenProvider: "' . $description . '" code: ' . $statusCode,
            $statusCode,
            $previous,
        );
    }
}
