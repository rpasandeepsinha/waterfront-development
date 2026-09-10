<?php

declare(strict_types=1);

namespace Waterfront\Infra\OpenSrsClient\Exceptions;

use RuntimeException;
use Throwable;

class OpenSrsResultException extends RuntimeException
{
    public function __construct(string $responseText, int $responseCode, ?Throwable $previous = null)
    {
        parent::__construct(
            'We got an error response from OpenSRS: "'
                . $responseText
                . '" code: '
                . $responseCode,
            $responseCode,
            $previous
        );
    }
}
