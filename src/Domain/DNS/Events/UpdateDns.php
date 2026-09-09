<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Events;

use Waterfront\Domain\DNS\Entities\DnsZoneDiff;

class UpdateDns
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
