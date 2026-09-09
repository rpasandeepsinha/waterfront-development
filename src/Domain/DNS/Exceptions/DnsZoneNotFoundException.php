<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Exceptions;

use Exception;

/**
 * @TODO move to CustomerSharedDnsService
 */
class DnsZoneNotFoundException extends Exception
{
    public string $zone;
}
