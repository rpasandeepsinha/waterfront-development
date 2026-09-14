<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Actions;

use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\DTO\Nameserver;
use Waterfront\Domain\DNS\Entities\DnsZone;
use Waterfront\Domain\DNS\Entities\DnsZoneDiff;
use Waterfront\Domain\DNS\Exceptions\DnsZoneNotFoundException;
use Waterfront\Domain\DNS\Interfaces\DnsZoneFactoryInterface;

/**
 * This action updates NS- and SOA-records in a zone if they contain one or more
 * legacy hostnames.
 */
class UpdateNameserverAndSoaAction
{
    public function __construct(
        private readonly DnsService $dnsService,
        private readonly DnsZoneFactoryInterface $dnsZoneFactory,
    ) {
    }

    /**
     * @param Nameserver[] $nameservers
     *
     * @throws GuzzleException
     * @throws JsonException
     */
    public function updateRecords(string $domainName, array $nameservers, bool $forceAdjustment = false): void
    {
        // Zone should exist at this point, if not silently fail
        try {
            $zone = $this->dnsService->getDnsZone($domainName);
        } catch (DnsZoneNotFoundException) {
            return;
        }

        if (! $forceAdjustment && ! $this->needsAdjustment($zone)) {
            return;
        }

        $diff = $this->calculateDiff($zone, $nameservers);
        $this->dnsService->applyDiffToZone($zone, $diff);
    }

    /**
     * Return true if the zone contains an NS- or SOA-record containing
     * a legacy hostname.
     */
    private function needsAdjustment(DnsZone $zone): bool
    {
        $soaRecords = $zone->getRecordsOfType('SOA');
        $nsRecords = $zone->getRecordsOfType('NS');

        if ($soaRecords === [] || $nsRecords === []) {
            return true;
        }

        $soa = $soaRecords[0];
        foreach ($this->getLegacyHostnames() as $legacyHostname) {
            if (str_contains($soa->getContent(), $legacyHostname)) {
                return true;
            }

            foreach ($nsRecords as $nsRecord) {
                if (str_contains($nsRecord->getContent(), $legacyHostname)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param Nameserver[] $nameservers
     */
    private function calculateDiff(DnsZone $zone, array $nameservers): DnsZoneDiff
    {
        $defaultZone = $this->dnsZoneFactory->create(
            domain: $zone->getFqdn()->withoutTrailingDot(),
            dnsTemplate: 'default',
            nameservers: $nameservers,
        );

        // Clone the zone so we can diff later
        $updatedZone = clone $zone;

        // Remove NS and SOA records
        foreach ($updatedZone->getRecordsOfType('SOA') as $record) {
            $updatedZone->removeRecord($record);
        }

        foreach ($updatedZone->getRecordsOfType('NS') as $record) {
            $updatedZone->removeRecord($record);
        }

        // Add new records
        foreach ($defaultZone->getRecordsOfType('SOA') as $record) {
            $updatedZone->addRecord($record);
        }

        foreach ($defaultZone->getRecordsOfType('NS') as $record) {
            $updatedZone->addRecord($record);
        }

        return $zone->diff($updatedZone);
    }

    /**
     * @return string[]
     */
    private function getLegacyHostnames(): array
    {
        return [
            '.2is.be',
            '.2is.nl',
            '.2is.nl',
            '.alfahost.nl',
            '.alphamega.eu',
            '.alphamega.nl',
            '.anony.eu',
            '.anony.nl',
            '.argewebhosting.com',
            '.argewebhosting.eu',
            '.argewebhosting.nl',
            '.auroradns.eu',
            '.auroradns.info',
            '.auroradns.nl',
            '.axc.eu',
            '.axc.nl',
            '.deziweb.com',
            '.dnssrv.nl',
            '.domeinbalie.nl',
            '.eatserver.nl',
            '.firstfind.net',
            '.firstfind.nl',
            '.flexwebhosting.com',
            '.flexwebhosting.nl',
            '.is.be',
            '.is.nl',
            '.naamserver.net',
            '.neostrada.nl',
            '.pcextreme.eu',
            '.pcextreme.nl',
            '.qdc.nl',
            '.resolver.domains',
            '.reviced.nl',
            '.secundairedns.nl',
            '.sitebytes.nl',
            '.sohosted.com',
            '.sohosted.net',
            '.sohosted47.com',
            '.versio.eu',
            '.versio.nl',
            '.vevida.com',
            '.vevida.net',
            '.vip.eu',
            '.vip.nl',
            '.yourhosting.eu',
            '.yourhosting.nl',
            '.yourhosting.nu',
        ];
    }
}
