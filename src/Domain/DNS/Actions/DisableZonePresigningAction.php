<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Actions;

use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\Exceptions\DnsZoneNotFoundException;
use Waterfront\Domain\DNS\Interfaces\DnsRecordInterface;
use Waterfront\Infra\PowerDnsClient\Exceptions\PdnsResponseException;

class DisableZonePresigningAction
{
    public function __construct(
        private readonly DnsService $dnsService,
    ) {
    }

    /**
     *
     * @throws DnsZoneNotFoundException
     * @throws GuzzleException
     * @throws JsonException
     * @throws PdnsResponseException
     */
    public function disable(string $domain): void
    {
        $zone = $this->dnsService->getDnsZone($domain);

        $originalRecords = $zone->getRecords();

        /** @var DnsRecordInterface[] $records */
        $records = [];

        foreach ($originalRecords as $record) {
            if (in_array($record->getType(), ['RRSIG', 'DNSKEY', 'SOA'], true)) {
                foreach ($records as $savedRecord) {
                    if (
                        $savedRecord->getName() === $record->getName()
                        && $savedRecord->getType() === $record->getType()
                    ) {
                        continue 2;
                    }
                }

                $records[] = $record;
            }
        }

        $this->dnsService->cleanupRecordsForPresigning($domain, $records);

        $this->dnsService->disablePresignedOnZone($zone);
    }
}
