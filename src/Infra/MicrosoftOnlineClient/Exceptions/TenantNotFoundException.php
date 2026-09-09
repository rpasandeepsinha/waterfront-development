<?php

declare(strict_types=1);

namespace Waterfront\Infra\MicrosoftOnlineClient\Exceptions;

use Exception;
use Throwable;

class TenantNotFoundException extends Exception
{
    public function __construct(string $tenantName, ?Throwable $previous = null)
    {
        parent::__construct(
            message: sprintf('Tenant "%s" does not exist.', $tenantName),
            previous: $previous,
        );
    }
}
