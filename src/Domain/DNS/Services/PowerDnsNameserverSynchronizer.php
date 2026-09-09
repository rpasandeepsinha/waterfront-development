<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Services;

use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\DTO\Nameserver;
use Waterfront\Domain\DNS\Entities\AddedDnsRecord;
use Waterfront\Domain\DNS\Entities\ChangedDnsRecord;
use Waterfront\Domain\DNS\Entities\RemovedDnsRecord;
use Waterfront\Domain\DNS\Exceptions\DnsZoneNotFoundException;
use Waterfront\Infra\PowerDnsClient\Enums\PowerDnsRecordChangeType;

class PowerDnsNameserverSynchronizer
{
    public function __construct(
        private readonly DnsService $dnsService,
        private readonly DnsZoneMutationCollector $dnsZoneMutationCollector,
    ) {
    }

    /**
     * @param Nameserver[] $nameservers
     *
     * @throws DnsZoneNotFoundException
     * @throws JsonException
     * @throws GuzzleException
     */
    public function synchronize(string $domain, array $nameservers, bool $sendNotify): void
    {
        $zone = $this->dnsService->getDnsZone($domain);

        $mutations = $this->dnsZoneMutationCollector->collectRequiredMutationsForNewNameservers($zone, $nameservers);

        $replaceNsRecords = [];
        $deleteNsRecords = [];

        foreach ($mutations as $mutation) {
            if ($mutation instanceof RemovedDnsRecord) {
                $deleteNsRecords[] = $mutation->getDnsRecord();
            } elseif ($mutation instanceof AddedDnsRecord) {
                // PowerDNS does not support 'add record' -> it always uses 'replace record'
                $replaceNsRecords[] = $mutation->getDnsRecord();
            } elseif ($mutation instanceof ChangedDnsRecord) {
                $replaceNsRecords[] = $mutation->getDnsRecord();
            }
        }

        if (count($deleteNsRecords) > 0) {
            $this->dnsService->updateDnsRecords($domain, $deleteNsRecords, PowerDnsRecordChangeType::DELETE);
        }

        if (count($replaceNsRecords) > 0) {
            $this->dnsService->updateDnsRecords($domain, $replaceNsRecords, PowerDnsRecordChangeType::REPLACE);
        }

        if ($sendNotify) {
            $this->dnsService->sendNotify($domain);
        }
    }
}
