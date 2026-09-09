<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Entities;

use Illuminate\Support\Arr;
use RuntimeException;
use Waterfront\Domain\DNS\Entities\DnsRecords\DefaultRecord;
use Waterfront\Domain\DNS\Exceptions\DnsZoneNoSOARecordException;
use Waterfront\Domain\DNS\Interfaces\DnsRecordInterface;
use Waterfront\Domain\DNS\ValueObjects\Fqdn;
use Waterfront\Infra\PowerDnsClient\Services\PowerDnsSoaSerialUpdater;

/**
 * DNS zone class used to communicate with the DNSService. It consists of a FQDN and a list of dns records.
 */
class DnsZone
{
    public ?string $kind = null;

    /**
     * @var DnsRecordInterface[]
     */
    private array $records = [];

    public function __construct(
        private readonly Fqdn $fqdn,
        public bool $dnsSec = false,
    ) {
    }

    public function getFqdn(): Fqdn
    {
        return $this->fqdn;
    }

    public function hasDnsSec(): bool
    {
        return $this->dnsSec;
    }

    /**
     * Apply a DNS record change.
     */
    public function applyChange(ChangedDnsRecord $change): self
    {
        $old = $change->getOldRecord();
        $new = $change->getDnsRecord();

        foreach ($this->records as $key => $record) {
            if (DnsCompare::equals($old, $record)) {
                $this->records[$key] = $new;

                return $this;
            }
        }

        // TODO: throw exception?
        return $this;
    }

    /**
     * Returns a diff between 2 DNS zone instances.
     */
    public function diff(self $other): DnsZoneDiff
    {
        if ($other->fqdn->toNative() !== $this->fqdn->toNative()) {
            throw new RuntimeException('A diff between two DNS zones can only be between the same fqdn!');
        }
        $changes = [];
        $unprocessedRecords = $this->records;
        foreach ($other->records as $record) {
            $type = $record->getType();
            $name = $record->getName();
            // find if there is an existing DNS record with the same type and name.
            $otherRecord = Arr::first(
                $unprocessedRecords,
                fn (DnsRecordInterface $contract): bool => strcasecmp($contract->getType(), $type) === 0
                    && strcasecmp($contract->getName(), $name) === 0
            );
            if (is_null($otherRecord)) {
                $changes[] = new AddedDnsRecord($record);
            } else {
                $unprocessedRecords = array_filter(
                    $unprocessedRecords,
                    fn (DnsRecordInterface $contract): bool => $contract !== $otherRecord
                );
                if (! DnsCompare::equals($record, $otherRecord)) {
                    $changes[] = new ChangedDnsRecord($otherRecord, $record);
                }
            }
        }
        foreach ($unprocessedRecords as $record) {
            $changes[] = new RemovedDnsRecord($record);
        }

        return new DnsZoneDiff($changes);
    }

    /**
     * @param DnsRecordInterface[] $records
     */
    public function setRecords(array $records): self
    {
        $this->records = [];
        foreach ($records as $record) {
            $this->addRecord($record);
        }

        return $this;
    }

    /**
     * Adds a DNS record.
     */
    public function addRecord(DnsRecordInterface $recordToAdd): self
    {
        // TODO Check FQDN match?
        foreach ($this->records as $record) {
            if (DnsCompare::equals($record, $recordToAdd)) {
                return $this;
            }
        }
        $this->records[] = $recordToAdd;

        return $this;
    }

    /**
     * Returns all DNS records.
     *
     * @return DnsRecordInterface[]
     */
    public function getRecords(): array
    {
        return $this->records;
    }

    /**
     * @return array<DnsRecordInterface>
     */
    public function getRecordsOfType(string $type): array
    {
        $records = [];

        foreach ($this->records as $record) {
            if ($record->getType() === $type) {
                $records[] = $record;
            }
        }

        return $records;
    }

    /**
     * @return array<DnsRecordInterface>
     */
    public function getRecordsOfTypeAndName(string $type, string $name): array
    {
        $records = [];

        foreach ($this->records as $record) {
            if ($record->getType() === $type && $record->getName() === $name) {
                $records[] = $record;
            }
        }

        return $records;
    }

    public function getRecordOfType(string $type): ?DnsRecordInterface
    {
        foreach ($this->records as $record) {
            if ($record->getType() === $type) {
                return $record;
            }
        }

        return null;
    }

    public function removeRecord(DnsRecordInterface $record): self
    {
        $this->records = array_values(
            array_filter(
                $this->records,
                fn (DnsRecordInterface $recordToCompare): bool => ! DnsCompare::equals($record, $recordToCompare)
            )
        );

        return $this;
    }

    public function updateSoa(): void
    {
        $oldSoaRecord = $this->getRecordOfType('SOA');

        if (! $oldSoaRecord instanceof DnsRecordInterface) {
            throw new DnsZoneNoSOARecordException($this);
        }

        $newSoaRecord = new DefaultRecord(
            type:'SOA',
            name: $oldSoaRecord->getName(),
            content: PowerDnsSoaSerialUpdater::increaseSoaSerial($oldSoaRecord->getContent()),
            ttl: $oldSoaRecord->getTtl() ?? 3600,
            disabled: false
        );

        $changedSoa = new ChangedDnsRecord($oldSoaRecord, $newSoaRecord);

        $this->applyChange($changedSoa);
    }

    /**
     * @return array<string, mixed[]|bool|Fqdn|string>
     */
    public function toArray(): array
    {
        $recordArray = [];

        foreach ($this->getRecords() as $record) {
            $recordArray[] = $record->toArray();
        }

        return [
            'fqdn' => $this->fqdn,
            'kind' => $this->kind ?? '',
            'dnsSec' => $this->hasDnsSec(),
            'records' => $recordArray,
        ];
    }
}
