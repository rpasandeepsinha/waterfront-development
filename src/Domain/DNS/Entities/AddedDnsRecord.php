<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Entities;

use Waterfront\Domain\DNS\Interfaces\DnsRecordInterface;
use Waterfront\Domain\DNS\Interfaces\DnsRecordMutationInterface;

/**
 * Used by DnsZoneDiff to mark an added DNS record.
 *
 * @see DnsZoneDiff
 */
class AddedDnsRecord implements DnsRecordMutationInterface
{
    public function __construct(private readonly DnsRecordInterface $added)
    {
    }

    public function getDnsRecord(): DnsRecordInterface
    {
        return $this->added;
    }

    public function apply(DnsZone $dnsZone): DnsZone
    {
        return $dnsZone->addRecord($this->added);
    }
}
