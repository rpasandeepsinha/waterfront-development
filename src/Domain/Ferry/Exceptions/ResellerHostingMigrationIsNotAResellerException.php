<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Exceptions;

use Exception;
use Throwable;

class ResellerHostingMigrationIsNotAResellerException extends Exception
{
    public function __construct(string $username, string $driver, string $hostname, ?Throwable $previous = null)
    {
        parent::__construct(
            sprintf(
                'Reseller hosting instance is not a reseller username {%s} on driver {%s} server {%s}',
                $username,
                $driver,
                $hostname,
            ),
            0,
            $previous
        );
    }
}
