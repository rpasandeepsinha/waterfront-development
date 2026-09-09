<?php

declare(strict_types=1);

namespace Waterfront\Infra\PowerDnsClient\Entities;

use Webmozart\Assert\Assert;

class PowerDnsZone
{
    /**
     * PowerDnsZone constructor.
     *
     * @param array<string, mixed>          $rawResponse
     * @param array<int, ResourceRecordSet> $rrsets
     */
    public function __construct(
        public ?string $id,
        public string $name,
        public array $rawResponse,
        public bool $dnssec = false,
        public string $kind = 'Master',
        private array $rrsets = []
    ) {
    }

    /**
     * @param ResourceRecordSet[] $recordSets
     */
    public function setRrsets(array $recordSets): self
    {
        $this->rrsets = [];
        foreach ($recordSets as $set) {
            $this->addRrset($set);
        }

        return $this;
    }

    /**
     * Adds a resource record set. This function does not check if the record set is already defined!
     */
    public function addRrset(ResourceRecordSet $recordSet): self
    {
        $this->rrsets[] = $recordSet;

        return $this;
    }

    /**
     * Adds a PowerDnsRecord.
     */
    public function addRecord(string $name, string $type, int $ttl, PowerDnsRecord $dnsRecord): self
    {
        $rrset = $this->findRecordSet($name, $type);
        $rrset->ttl = $ttl;
        $rrset->addRecord($dnsRecord);

        return $this;
    }

    /**
     * Finds a DNS record set with the same name and type. This is how PowerDNS groups DNS records.
     */
    public function findRecordSet(string $name, string $type): ResourceRecordSet
    {
        foreach ($this->rrsets as $recordSet) {
            if ($recordSet->isEqual($name, $type)) {
                return $recordSet;
            }
        }
        $rrset = new ResourceRecordSet();
        $rrset->name = $name;
        $rrset->type = $type;
        $this->addRrset($rrset);

        return $rrset;
    }

    /**
     * Gets the PowerDNS Record set. A Record set contains a ttl and a list of all dns records with the same name
     * and type.
     *
     * @return ResourceRecordSet[]
     */
    public function getRrsets(): array
    {
        return $this->rrsets;
    }

    /**
     * @see https://doc.powerdns.com/authoritative/http-api/zone.html#zone
     *
     * Use Master here to enforce Notify.
     */
    public function getKind(): string
    {
        return $this->kind;
    }

    /**
     * @see https://doc.powerdns.com/authoritative/http-api/zone.html#zone
     *
     * @return string[]
     */
    public function getNameservers(): array
    {
        return [];
    }

    public function hasDnsSec(): bool
    {
        return $this->dnssec;
    }

    /**
     * @return array{
     *       id: ?string,
     *       name: string,
     *       kind: string,
     *       rrsets: array<int, array{
     *          records: array<array{
     *               content: string,
     *               disabled: bool,
     *            }>,
     *          name: string,
     *          type: string,
     *          ttl: ?int,
     *          changetype: string,
     *       }>,
     *       dnssec: bool,
     *       api_rectify: true,
     *   }
     */
    public function toArray(): array
    {
        $rrsets = array_map(static fn (ResourceRecordSet $rrset) => [
            'records' => array_map(static fn (PowerDnsRecord $record) => $record->toArray(), $rrset->getRecords()),
            'name' => $rrset->name,
            'type' => $rrset->type,
            'ttl' => $rrset->ttl,
            'changetype' => $rrset->getChangetype(),
        ], $this->rrsets);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'kind' => $this->getKind(),
            'rrsets' => $rrsets,
            'dnssec' => $this->dnssec,
            'api_rectify' => true, /* See bottom paragraphs of https://doc.powerdns.com/authoritative/http-api/zone.html */
        ];
    }

    /**
     * @param array<mixed, mixed> $pdnsZone
     */
    public static function fromArray(array $pdnsZone): self
    {
        Assert::keyExists($pdnsZone, 'id');
        Assert::keyExists($pdnsZone, 'name');
        Assert::keyExists($pdnsZone, 'dnssec');
        Assert::keyExists($pdnsZone, 'kind');
        $kind = $pdnsZone['kind'];
        Assert::string($kind);

        $resourcedSets = [];

        if (array_key_exists('rrsets', $pdnsZone) && is_array($pdnsZone['rrsets'])) {
            foreach ($pdnsZone['rrsets'] as $rrset) {
                Assert::isArray($rrset);
                Assert::keyExists($rrset, 'name');
                Assert::keyExists($rrset, 'type');
                Assert::keyExists($rrset, 'ttl');

                $rrsetEntity = new ResourceRecordSet();
                $rrsetEntity->name = $rrset['name'];
                $rrsetEntity->type = $rrset['type'];
                $rrsetEntity->ttl = $rrset['ttl'];

                foreach ($rrset['records'] as $record) {
                    Assert::keyExists($record, 'content');
                    Assert::keyExists($record, 'disabled');

                    $recordEntity           = new PowerDnsRecord();
                    $recordEntity->content  = $record['content'];
                    $recordEntity->disabled = $record['disabled'];

                    $rrsetEntity->addRecord($recordEntity);
                }

                $resourcedSets[] = $rrsetEntity;
            }
        }

        $id = $pdnsZone['id'];
        $name = $pdnsZone['name'];
        $dnssec = $pdnsZone['dnssec'];

        Assert::nullOrString($id);
        Assert::string($name);
        Assert::boolean($dnssec);

        return new self(
            id: $id,
            name: $name,
            rawResponse: $pdnsZone,
            dnssec: $dnssec,
            kind: $kind,
            rrsets: $resourcedSets
        );
    }
}
