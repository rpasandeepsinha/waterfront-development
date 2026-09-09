<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Exceptions\Services\DnsNameserverRetriever;

use RuntimeException;
use Throwable;

class DnsRegionNotFoundException extends RuntimeException
{
    public function __construct(string $message = '', ?Throwable $previous = null)
    {
        parent::__construct(
            sprintf(
                'DnsRegion not found: %s',
                $message,
            ),
            0,
            $previous,
        );
    }
}
