<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Exceptions;

use RuntimeException;
use Throwable;

class InvalidVanityNameserverConfigException extends RuntimeException
{
    /**
     * @param string         $configKey invalid or missing config key
     * @param Throwable|null $previous  The original exception if any
     */
    public function __construct(string $configKey, ?Throwable $previous = null)
    {
        parent::__construct(
            sprintf('Invalid or missing DNS vanity nameserver configuration for key "%s".', $configKey),
            0,
            $previous,
        );
    }
}
