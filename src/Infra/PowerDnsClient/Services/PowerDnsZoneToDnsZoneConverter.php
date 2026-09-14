<?php

declare(strict_types=1);

namespace Waterfront\Infra\PowerDnsClient\Services;

use Waterfront\Domain\DNS\Entities\DnsZone;
use Waterfront\Domain\DNS\Entities\DnsZoneDiff;
use Waterfront\Domain\DNS\Hydrators\DnsRecordHydrator;
use Waterfront\Domain\DNS\Interfaces\ContentWithDotInterface;
use Waterfront\Domain\DNS\Interfaces\DnsRecordInterface;
use Waterfront\Domain\DNS\Services\Serializers\PowerDnsRecordContentSerializer;
use Waterfront\Domain\DNS\ValueObjects\Fqdn;
use Waterfront\Infra\PowerDnsClient\Entities\PowerDnsRecord;
use Waterfront\Infra\PowerDnsClient\Entities\PowerDnsZone;
use Waterfront\Infra\PowerDnsClient\Entities\ResourceRecordSet;

class PowerDnsZoneToDnsZoneConverter
{
    public function __construct(
        private readonly DnsRecordHydrator $dnsRecordHydrator,
        private readonly PowerDnsRecordContentSerializer $serializer,
    ) {
    }

    public function convertFromPowerDnsZone(PowerDnsZone $powerDnsZone, bool $validate = false): DnsZone
    {
        $dnsZone = new DnsZone(new Fqdn($powerDnsZone->id ?? $powerDnsZone->name));

        $dnsZone->dnsSec = $powerDnsZone->hasDnsSec();
        $dnsZone->kind = $powerDnsZone->kind;

        foreach ($powerDnsZone->getRrsets() as $recordSet) {
            foreach ($recordSet->getRecords() as $record) {
                $dnsZone->addRecord($this->convertToDnsRecord($recordSet, $record, $validate));
            }
        }

        return $dnsZone;
    }

    public function convertToPowerDnsZone(DnsZone $dnsZone, ?DnsZoneDiff $diff = null): PowerDnsZone
    {
        $powerDnsZone = new PowerDnsZone(
            id: $dnsZone->getFqdn()->toNative(),
            name: $dnsZone->getFqdn()->toNative(),
            rawResponse: [],
            dnssec: $dnsZone->hasDnsSec(),
        );

        foreach ($dnsZone->getRecords() as $record) {
            $powerDnsZone->addRecord(
                $record->getName() . '.',
                $record->getType(),
                $record->getTtl() ?? 86400,
                $this->convertToPowerDnsRecord($record),
            );
        }

        // the $diff is only needed for removing rows. If a resource record set would disappear, we would still
        // need to write it to the patch call to empty it.
        if ($diff !== null) {
            foreach ($diff->getRemovedRows() as $removedRow) {
                $record = $removedRow->getDnsRecord();
                $powerDnsZone->findRecordSet($record->getName() . '.', $record->getType());
            }
        }

        return $powerDnsZone;
    }

    private function convertToDnsRecord(
        ResourceRecordSet $recordSet,
        PowerDnsRecord $record,
        bool $validate,
    ): DnsRecordInterface {
        $data = $this->serializer->unserialize($recordSet->type, $record->content);
        $data['name'] = $recordSet->name;
        $data['disabled'] = $record->disabled;
        $data['type'] = $recordSet->type;
        $data['ttl'] = $recordSet->ttl;

        /**
         * Only gets called from the context of creating a new clean zone or one from a
         * pre-validated template or from the generic getZone functionality.
         */
        return $this->dnsRecordHydrator->hydrate($data, $validate);
    }

    private function convertToPowerDnsRecord(DnsRecordInterface $record): PowerDnsRecord
    {
        $suffix = $record instanceof ContentWithDotInterface ? '.' : '';
        $result = new PowerDnsRecord();
        $data = $this->dnsRecordHydrator->dehydrate($record);
        $result->content = $this->serializer->serialize($record->getType(), $data) . $suffix;
        $result->disabled = $record->isDisabled();

        return $result;
    }
}
