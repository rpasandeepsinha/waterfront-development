<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Services;

use LogicException;
use RuntimeException;
use Waterfront\Domain\DNS\DTO\Nameserver;
use Waterfront\Domain\DNS\Entities\AddedDnsRecord;
use Waterfront\Domain\DNS\Entities\ChangedDnsRecord;
use Waterfront\Domain\DNS\Entities\DnsRecords\DefaultRecord;
use Waterfront\Domain\DNS\Entities\DnsZone;
use Waterfront\Domain\DNS\Entities\RemovedDnsRecord;
use Waterfront\Domain\DNS\Exceptions\DnsZoneNoSOARecordException;
use Waterfront\Domain\DNS\Interfaces\DnsRecordMutationInterface;
use Waterfront\Infra\PowerDnsClient\Services\PowerDnsSoaSerialUpdater;

class DnsZoneMutationCollector
{
    /**
     * @param Nameserver[] $nameservers
     *
     * @return array<DnsRecordMutationInterface>
     */
    public function collectRequiredMutationsForNewNameservers(DnsZone $zone, array $nameservers): array
    {
        if (count($nameservers) === 0) {
            throw new LogicException('Provided nameservers is empty');
        }

        $mutations = self::collectNameserverMutations($zone, $nameservers);
        $mutations[] = self::collectSoaMutation($zone, $nameservers[0]);

        return $mutations;
    }

    /**
     * @param non-empty-array<Nameserver> $nameservers
     *
     * @return array<DnsRecordMutationInterface>
     */
    private function collectNameserverMutations(DnsZone $zone, array $nameservers): array
    {
        $domain = $zone->getFqdn()->withoutTrailingDot();
        $zoneRootNameservers = $zone->getRecordsOfTypeAndName('NS', $domain);

        /** @var array<DnsRecordMutationInterface> $mutations */
        $mutations = [];
        $nameserversToUpdate = array_slice($nameservers, 0, count($zoneRootNameservers));

        $needsToRemoveRecords = count($zoneRootNameservers) > count($nameservers);
        $needsToAddRecords = count($nameservers) > count($zoneRootNameservers);

        foreach ($nameserversToUpdate as $index => $nameserver) {
            $mutations[] = new ChangedDnsRecord(
                $zoneRootNameservers[$index],
                self::createNameserverRecord($domain, $nameservers[$index]),
            );
        }

        if ($needsToRemoveRecords) {
            $nameserversToDelete = array_slice($zoneRootNameservers, count($nameservers));

            foreach ($nameserversToDelete as $nameserver) {
                $mutations[] = new RemovedDnsRecord($nameserver);
            }
        } elseif ($needsToAddRecords) {
            /*
             * Only add missing records so DNS provider has the same amount of NS records as $nameservers,
             * existing records will be updated to provided $nameservers where possible
             */
            $nameserversToAdd = array_slice($nameservers, count($zoneRootNameservers));

            foreach ($nameserversToAdd as $nameserver) {
                $mutations[] = new AddedDnsRecord(self::createNameserverRecord($domain, $nameserver));
            }
        }

        return $mutations;
    }

    private static function collectSoaMutation(DnsZone $zone, Nameserver $primaryNameserver): ChangedDnsRecord
    {
        $soaRecord = $zone->getRecordOfType('SOA');

        if ($soaRecord === null) {
            throw new DnsZoneNoSOARecordException($zone);
        }

        $content = self::insertNameserverIntoSOAContent($soaRecord->getContent(), $primaryNameserver);

        return new ChangedDnsRecord(
            $soaRecord,
            new DefaultRecord(
                'SOA',
                $soaRecord->getName(),
                PowerDnsSoaSerialUpdater::increaseSoaSerial($content),
                $soaRecord->getTtl() ?? 3600,
                disabled: false,
            ),
        );
    }

    private static function createNameserverRecord(string $domain, Nameserver $nameserver): DefaultRecord
    {
        return new DefaultRecord(
            'NS',
            $domain,
            "$nameserver->hostname.",
            3600,
            disabled: false,
        );
    }

    private static function insertNameserverIntoSOAContent(string $content, Nameserver $nameserver): string
    {
        $nameserverWithSpacePos = mb_strpos($content, ' ');

        if ($nameserverWithSpacePos === false) {
            throw new RuntimeException("Invalid content in SOA record: $content");
        }

        $content = substr($content, $nameserverWithSpacePos + 1);

        return "$nameserver->hostname. $content";
    }
}
