<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Entities;

use Waterfront\Domain\DNS\Interfaces\DnsRecordInterface;
use Waterfront\Domain\DNS\Interfaces\DnsRecordMutationInterface;

/**
 * Used by DnsZoneDiff to indicate an existing dns record was changed.
 *
 * @see DnsZoneDiff
 */
class ChangedDnsRecord implements DnsRecordMutationInterface
{
    public function __construct(
        private readonly DnsRecordInterface $old,
        private readonly DnsRecordInterface $new,
    ) {
    }

    public function getOldRecord(): DnsRecordInterface
    {
        return $this->old;
    }

    public function getDnsRecord(): DnsRecordInterface
    {
        return $this->new;
    }

    public function apply(DnsZone $dnsZone): DnsZone
    {
        return $dnsZone->applyChange($this);
    }
}
