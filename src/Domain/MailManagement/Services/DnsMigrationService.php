<?php

declare(strict_types=1);

namespace Waterfront\Domain\MailManagement\Services;

use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use JsonException;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\Entities\DnsRecords\ARecord;
use Waterfront\Domain\DNS\Entities\DnsRecords\MxRecord;
use Waterfront\Domain\DNS\Exceptions\DnsZoneNotFoundException;
use Waterfront\Domain\DNS\Interfaces\DnsRecordInterface;
use Waterfront\Infra\PowerDnsClient\Exceptions\PdnsResponseException;
use Waterfront\Support\Enums\LoggingContextKeys;

class DnsMigrationService
{
    public const PRIMARY_PRIO = 10;

    public const FALLBACK_PRIO = 20;

    public const RECORD_TTL = 3600;

    public function __construct(
        private readonly DnsService $dns,
    ) {
    }

    /**
     * @throws DnsZoneNotFoundException
     * @throws PdnsResponseException
     * @throws GuzzleException
     * @throws JsonException
     */
    public function setARecord(string $domain, string $ipAddress): void
    {
        $records = $this->dns->getDnsRecordsForDomain($domain);

        $aMailRecords = $records
            ->filter(fn (DnsRecordInterface $record) => $record instanceof ARecord)
            ->each(function (ARecord $record) use ($domain): void {
                if ($record->getName() === 'mail.' . $domain) {
                    $this->dns->deleteRecordFromObject($domain, $record);
                }
            });

        Log::debug(
            sprintf(
                'Found %d auto-configured A `mail.%s` records for zone %s. Cleaned up.',
                $aMailRecords->count(),
                $domain,
                $domain,
            ),
            $aMailRecords->toArray(),
        );

        $this->addARecord($domain, $ipAddress);
    }

    /**
     * @throws GuzzleException
     * @throws DnsZoneNotFoundException
     * @throws JsonException
     * @throws PdnsResponseException
     */
    public function setMxRecords(string $domain, string $primaryHost, string $fallbackHost): void
    {
        $records = $this->dns->getDnsRecordsForDomain($domain);

        $this->deleteAllMxRecords($domain, $records);
        $this->addMxRecords($domain, $primaryHost, $fallbackHost);
    }

    /**
     * @throws DnsZoneNotFoundException
     * @throws PdnsResponseException
     * @throws GuzzleException
     * @throws JsonException
     */
    public function removeMxRecords(string $domain, string $primaryHost, string $fallbackHost): void
    {
        $records = $this->dns->getDnsRecordsForDomain($domain);

        $mxRecords = $records
            ->filter(fn (DnsRecordInterface $record) => $record instanceof MxRecord)
            ->filter(fn (MxRecord $record) => in_array($record->getContent(), [$primaryHost, $fallbackHost], true))
            ->each(function (MxRecord $record) use ($domain): void {
                $this->dns->deleteRecordFromObject($domain, $record);
            });
        Log::debug(
            "DnsPremium: Found {$mxRecords->count()} auto-configured MX records for zone {$domain}. Cleaned up.",
            [
                LoggingContextKeys::META => [
                    'mxrecords' => $mxRecords->toArray(),
                ],
            ],
        );
    }

    /**
     * @param Collection<int, DnsRecordInterface> $records
     *
     * @throws DnsZoneNotFoundException
     * @throws PdnsResponseException
     * @throws GuzzleException
     * @throws JsonException
     */
    private function deleteAllMxRecords(string $domain, Collection $records): void
    {
        $mxRecords = $records
            ->filter(fn (DnsRecordInterface $record) => $record instanceof MxRecord)
            ->each(function (MxRecord $record) use ($domain): void {
                $this->dns->deleteRecordFromObject($domain, $record);
            });

        Log::debug(
            "DnsPremium: Found {$mxRecords->count()} pre-existing MX records for zone {$domain}. Cleaned up.",
            [
                LoggingContextKeys::META => [
                    'mxrecords' => $mxRecords->toArray(),
                ],
            ],
        );
    }

    /**
     * @throws DnsZoneNotFoundException
     * @throws PdnsResponseException
     * @throws GuzzleException
     * @throws JsonException
     */
    private function addMxRecords(string $domain, string $primaryHost, string $fallbackHost): void
    {
        Log::debug("Setting auto-configured MX records on zone {$domain}..");
        $this->dns->addRecordFromObject(
            $domain,
            new MxRecord($domain, $primaryHost, self::PRIMARY_PRIO, self::RECORD_TTL),
        );
        $this->dns->addRecordFromObject(
            $domain,
            new MxRecord($domain, $fallbackHost, self::FALLBACK_PRIO, self::RECORD_TTL),
        );
    }

    /**
     * @throws DnsZoneNotFoundException
     * @throws PdnsResponseException
     * @throws GuzzleException
     * @throws JsonException
     */
    private function addARecord(string $domain, string $ipAddress): void
    {
        $this->dns->addRecordFromObject($domain, new ARecord('mail.' . $domain, $ipAddress, self::RECORD_TTL));
    }
}
