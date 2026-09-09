<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Exceptions;

use Exception;
use Throwable;
use Waterfront\Domain\DNS\Entities\DnsZone;

class DnsZoneNoSOARecordException extends Exception
{
    public function __construct(DnsZone $dnsZone, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct(
            sprintf(
                'Failed to find SOA record for zone: %s',
                $dnsZone->getFqdn()->withoutTrailingDot(),
            ),
            $code,
            $previous
        );
    }
}
