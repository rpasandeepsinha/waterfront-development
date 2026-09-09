<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Events;

use Waterfront\Domain\DNS\Entities\DnsZoneDiff;

/**
 * Applies DNS changes for a hosting flow, first stripping any parking ('default') template
 * A/AAAA records so a previously parked domain does not keep them alongside the new records.
 */
class ReplaceParkingAndUpdateDns
{
    public function __construct(
        private readonly string $domain,
        private readonly DnsZoneDiff $changes,
    ) {
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function getChanges(): DnsZoneDiff
    {
        return $this->changes;
    }
}
