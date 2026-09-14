<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Entities;

use Waterfront\Domain\DNS\Interfaces\DnsRecordInterface;
use Waterfront\Domain\DNS\Interfaces\DnsRecordMutationInterface;

/**
 * Used by DnsZoneDiff to mark a removed DNS record.
 *
 * @see DnsZoneDiff
 */
class RemovedDnsRecord implements DnsRecordMutationInterface
{
    public function __construct(
        private readonly DnsRecordInterface $removed,
    ) {
    }

    public function getDnsRecord(): DnsRecordInterface
    {
        return $this->removed;
    }

    public function apply(DnsZone $dnsZone): DnsZone
    {
        return $dnsZone->removeRecord($this->removed);
    }
}
