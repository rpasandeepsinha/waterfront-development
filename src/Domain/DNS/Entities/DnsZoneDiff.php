<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Entities;

use Waterfront\Domain\DNS\Interfaces\DnsRecordMutationInterface;

/**
 * Represents a diff between two DnsZone instances.
 *
 * @see DnsZone::diff()
 */
class DnsZoneDiff
{
    /**
     * @param DnsRecordMutationInterface[] $changes
     */
    public function __construct(private readonly array $changes)
    {
    }

    /**
     * @return DnsRecordMutationInterface[]
     */
    public function getChanges(): array
    {
        return $this->changes;
    }

    /**
     * @return AddedDnsRecord[]
     */
    public function getAddedRows(): array
    {
        return array_filter(
            $this->changes,
            fn (DnsRecordMutationInterface $diff): bool => $diff instanceof AddedDnsRecord
        );
    }

    /**
     * @return RemovedDnsRecord[]
     */
    public function getRemovedRows(): array
    {
        return array_filter(
            $this->changes,
            fn (DnsRecordMutationInterface $diff): bool => $diff instanceof RemovedDnsRecord
        );
    }

    /**
     * @return ChangedDnsRecord[]
     */
    public function getChangedRows(): array
    {
        return array_filter(
            $this->changes,
            fn (DnsRecordMutationInterface $diff): bool => $diff instanceof ChangedDnsRecord
        );
    }
}
