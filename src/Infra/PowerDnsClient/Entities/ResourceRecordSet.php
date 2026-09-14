<?php

declare(strict_types=1);

namespace Waterfront\Infra\PowerDnsClient\Entities;

class ResourceRecordSet
{
    public string $name;

    public string $type;

    /**
     * Value should only be NULL if changetype is DELETE.
     */
    public ?int $ttl = null;

    /**
     * @var PowerDnsRecord[]
     */
    private array $records = [];

    public function addRecord(PowerDnsRecord $record): void
    {
        $this->records[] = $record;
    }

    /**
     * Required for PATCH calls. If records is empty we indicate the records need to be deleted.
     */
    public function getChangetype(): string
    {
        return $this->records === [] ? 'DELETE' : 'REPLACE';
    }

    /**
     * @return PowerDnsRecord[]
     */
    public function getRecords(): array
    {
        return $this->records;
    }

    /**
     * Returns true if the name and type are the same. The check is case insensitive and ignores trailing dots.
     */
    public function isEqual(string $name, string $type): bool
    {
        return (
            strcasecmp(rtrim($name, '.'), rtrim($this->name, '.')) === 0
            && strcasecmp(rtrim($type, '.'), rtrim($this->type, '.')) === 0
        );
    }
}
