<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Exceptions;

use Exception;
use Throwable;

class DnsDeploymentNotFoundException extends Exception
{
    public function __construct(string $domain, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct(
            sprintf(
                'Dns deployment for domain: %s not found.',
                $domain,
            ),
            $code,
            $previous,
        );
    }
}
