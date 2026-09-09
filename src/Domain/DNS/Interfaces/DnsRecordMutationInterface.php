<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Interfaces;

use Waterfront\Domain\DNS\Entities\AddedDnsRecord;
use Waterfront\Domain\DNS\Entities\ChangedDnsRecord;
use Waterfront\Domain\DNS\Entities\DnsZone;
use Waterfront\Domain\DNS\Entities\DnsZoneDiff;
use Waterfront\Domain\DNS\Entities\RemovedDnsRecord;

/**
 * All changes of a DnsZoneDiff should implement this interfaces.
 *
 * @see DnsZoneDiff::getChanges()
 * @see AddedDnsRecord
 * @see ChangedDnsRecord
 * @see RemovedDnsRecord
 */
interface DnsRecordMutationInterface
{
    public function getDnsRecord(): DnsRecordInterface;

    public function apply(DnsZone $dnsZone): DnsZone;
}
