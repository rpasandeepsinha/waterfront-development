<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS;

use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use JsonException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Waterfront\Domain\DNS\DTO\Nameserver;
use Waterfront\Domain\DNS\Entities\ChangedDnsRecord;
use Waterfront\Domain\DNS\Entities\DnsRecords\AbstractRecord;
use Waterfront\Domain\DNS\Entities\DnsRecordTypes;
use Waterfront\Domain\DNS\Entities\DnsZone;
use Waterfront\Domain\DNS\Entities\DnsZoneDiff;
use Waterfront\Domain\DNS\Enums\DnsChangeType;
use Waterfront\Domain\DNS\Exceptions\DnsZoneAlreadyCreatedException;
use Waterfront\Domain\DNS\Exceptions\DnsZoneNotFoundException;
use Waterfront\Domain\DNS\Hydrators\DnsRecordHydrator;
use Waterfront\Domain\DNS\Interfaces\DnsRecordInterface;
use Waterfront\Domain\DNS\Interfaces\DnsZoneFactoryInterface;
use Waterfront\Domain\DNS\Jobs\UpdateNameservers;
use Waterfront\Domain\DNS\Models\DnsCustomerTemplate;
use Waterfront\Domain\DNS\Models\DnsCustomerTemplateRecord;
use Waterfront\Domain\DNS\Services\DnsLogService;
use Waterfront\Domain\DNS\Services\DnsRecordConverter;
use Waterfront\Domain\DNS\Services\DnsZoneService;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\PowerDnsClient\Entities\PowerDnsMetadata;
use Waterfront\Infra\PowerDnsClient\Entities\PowerDnsSecKey;
use Waterfront\Infra\PowerDnsClient\Entities\PowerDnsSecKeySet;
use Waterfront\Infra\PowerDnsClient\Enums\PowerDnsMetadataType;
use Waterfront\Infra\PowerDnsClient\Enums\PowerDnsRecordChangeType;
use Waterfront\Infra\PowerDnsClient\Enums\PowerDnsZoneKind;
use Waterfront\Infra\PowerDnsClient\Exceptions\PdnsResponseException;
use Waterfront\Infra\PowerDnsClient\Exceptions\PdnsValidationException;
use Waterfront\Infra\PowerDnsClient\PowerDnsClient;
use Waterfront\Support\Enums\LoggingContextKeys;

/**
 * Entry point for doing DNS record changes. Right now only PowerDNS is supported, but other formats will be
 * relatively easy to add.
 */
class DnsService
{
    private const int MINIMUM_TTL_PREMIUM_DNS = 300;

    public function __construct(
        private readonly PowerDnsClient $powerDnsClient,
        private readonly DnsZoneFactoryInterface $dnsZoneFactory,
        private readonly ConfigurationInterface $configuration,
        private readonly Dispatcher $jobDispatcher,
        private readonly LoggerInterface $logger,
        private readonly DnsRecordHydrator $hydrator,
        private readonly DnsLogService $dnsLogService,
        private readonly DnsRecordConverter $dnsRecordConverter,
        private readonly DnsZoneService $dnsZoneService,
    ) {
    }

    /**
     * @throws DnsZoneNotFoundException
     * @throws JsonException
     * @throws GuzzleException
     */
    public function getDnsZone(string $domain): DnsZone
    {
        return $this->powerDnsClient->getZone($domain);
    }

    public function hasDnsZone(string $domain): bool
    {
        try {
            $this->getDnsZone($domain);

            return true;
        } catch (DnsZoneNotFoundException) {
            return false;
        }
    }

    public function isSlaveZone(string $domain): bool
    {
        try {
            $zone = $this->getDnsZone($domain);

            if ($zone->kind === PowerDnsZoneKind::SLAVE->value) {
                return true;
            }

            return false;
        } catch (DnsZoneNotFoundException) {
            return false;
        }
    }

    /**
     * Creates a dns zone with a DNS template.
     *
     * @param Nameserver[] $nameservers
     *
     * @throws JsonException
     * @throws PdnsValidationException
     * @throws DnsZoneAlreadyCreatedException
     * @throws PdnsResponseException
     * @throws GuzzleException
     */
    public function createDnsZone(
        string $domain,
        string $dnsTemplate = 'default',
        ?string $ipv4 = null,
        ?string $ipv6 = null,
        bool $dnsSec = false,
        array $nameservers = [],
    ): DnsZone {
        $dnsZone = $this->dnsZoneFactory->create(
            $domain,
            $dnsTemplate,
            $ipv4,
            $ipv6,
            null,
            null,
            $dnsSec,
            $nameservers,
        );

        $dnsZone = $this->powerDnsClient->createZone($dnsZone);

        $this->dnsLogService->logMultipleRecordsOfDnsZone($dnsZone, $domain, DnsChangeType::CREATED);

        return $dnsZone;
    }

    /**
     * @throws DnsZoneNotFoundException
     * @throws GuzzleException
     * @throws JsonException
     * @throws PdnsResponseException
     */
    public function addDnsRecord(string $domain, DnsRecordInterface $record): DnsZone
    {
        $dnsZone = $this->powerDnsClient->addDnsRecord($domain, $record);

        $this->dnsLogService->logSingleRecordOfDnsZone($record, $domain, DnsChangeType::CREATED);

        return $dnsZone;
    }

    /**
     * Removes a dns record if the record is in the DNS zone.
     *
     * @throws JsonException
     * @throws GuzzleException
     * @throws DnsZoneNotFoundException
     * @throws PdnsResponseException
     */
    public function removeDnsRecord(
        string $domain,
        DnsRecordInterface $record,
    ): DnsZone {
        $dnsZone = $this->powerDnsClient->removeDnsRecord($domain, $record);

        $this->dnsLogService->logSingleRecordOfDnsZone($record, $domain, DnsChangeType::DELETED);

        return $dnsZone;
    }

    /**
     * Change one DNS record in the DNS zone.
     *
     * @throws DnsZoneNotFoundException
     * @throws JsonException
     * @throws GuzzleException
     * @throws PdnsResponseException
     */
    public function updateRecord(string $domain, ChangedDnsRecord $change): DnsZone
    {
        $dnsZone = $this->powerDnsClient->getZone($domain);
        $dnsZone->applyChange($change);

        $changedDnsZone = $this->powerDnsClient->changeZone($dnsZone);

        $this->dnsLogService->logSingleRecordOfDnsZone($change->getOldRecord(), $domain, DnsChangeType::DELETED);
        $this->dnsLogService->logSingleRecordOfDnsZone($change->getDnsRecord(), $domain, DnsChangeType::CREATED);

        return $changedDnsZone;
    }

    /**
     * @param DnsRecordInterface[] $records
     *
     * @throws JsonException
     */
    public function updateDnsRecords(string $domain, array $records, PowerDnsRecordChangeType $changeType): void
    {
        $this->powerDnsClient->changeDnsRecords($domain, $records, $changeType);

        foreach ($records as $record) {
            $this->dnsLogService->logSingleRecordOfDnsZone($record, $domain, DnsChangeType::DELETED);
            $this->dnsLogService->logSingleRecordOfDnsZone($record, $domain, DnsChangeType::CREATED);
        }
    }

    /**
     * Apply a set of changes.
     */
    public function applyDiff(string $domain, DnsZoneDiff $diff): DnsZone
    {
        $this->logger->info(
            'Updating zone',
            [
                LoggingContextKeys::DOMAIN_NAME => $domain,
                LoggingContextKeys::META => [
                    'diff' => $diff->getChangedRows(),
                ],
            ],
        );

        $dnsZone = $this->powerDnsClient->getZone($domain);

        return $this->applyDiffToZone($dnsZone, $diff);
    }

    /**
     * Apply a set of changes after first stripping the parking ('default') template's A/AAAA records.
     *
     * Used by hosting DNS flows so a domain that was parked does not keep its parking address
     * records alongside the newly applied hosting records.
     *
     * @throws DnsZoneNotFoundException
     * @throws GuzzleException
     * @throws JsonException
     * @throws PdnsResponseException
     */
    public function applyDiffReplacingParkingRecords(string $domain, DnsZoneDiff $diff): DnsZone
    {
        $this->logger->info(
            'Updating zone and replacing parking records',
            [
                LoggingContextKeys::DOMAIN_NAME => $domain,
                LoggingContextKeys::META => [
                    'diff' => $diff->getChangedRows(),
                ],
            ],
        );

        $dnsZone = $this->powerDnsClient->getZone($domain);
        $this->removeParkingDnsRecords($domain, $dnsZone);

        return $this->applyDiffToZone($dnsZone, $diff);
    }

    /**
     * @throws GuzzleException
     * @throws JsonException
     *
     * @return DnsRecordInterface[]
     */
    public function getConflictingParkingRecords(string $domain): array
    {
        try {
            $dnsZone = $this->getDnsZone($domain);
        } catch (DnsZoneNotFoundException) {
            return [];
        }

        if ($dnsZone->kind === PowerDnsZoneKind::SLAVE->value) {
            return [];
        }

        return $this->findConflictingParkingRecords($domain, $dnsZone);
    }

    /**
     * @throws GuzzleException
     * @throws JsonException
     * @throws PdnsResponseException
     * @throws DnsZoneNotFoundException
     *
     * @return DnsRecordInterface[]
     */
    public function removeConflictingParkingRecords(string $domain): array
    {
        $dnsZone = $this->getDnsZone($domain);

        if ($dnsZone->kind === PowerDnsZoneKind::SLAVE->value) {
            return [];
        }

        $conflictingRecords = $this->findConflictingParkingRecords($domain, $dnsZone);
        if ($conflictingRecords === []) {
            return [];
        }

        foreach ($conflictingRecords as $conflictingRecord) {
            $dnsZone->removeRecord($conflictingRecord);
        }

        $this->powerDnsClient->changeZone($dnsZone);

        foreach ($conflictingRecords as $conflictingRecord) {
            $this->dnsLogService->logSingleRecordOfDnsZone($conflictingRecord, $domain, DnsChangeType::DELETED);
        }

        return $conflictingRecords;
    }

    public function applyDiffToZone(DnsZone $zone, DnsZoneDiff $diff): DnsZone
    {
        foreach ($diff->getChanges() as $mutation) {
            $mutation->apply($zone);
        }

        $changedDnsZone = $this->powerDnsClient->changeZone($zone);
        $domain = $zone->getFqdn()->toNative();

        foreach ($diff->getRemovedRows() as $removedRow) {
            $this->dnsLogService->logSingleRecordOfDnsZone(
                $removedRow->getDnsRecord(),
                $domain,
                DnsChangeType::DELETED,
            );
        }

        foreach ($diff->getAddedRows() as $addedRow) {
            $this->dnsLogService->logSingleRecordOfDnsZone($addedRow->getDnsRecord(), $domain, DnsChangeType::CREATED);
        }

        return $changedDnsZone;
    }

    /**
     * Get all DNSSEC keys for zone.
     */
    public function getDnsZoneKeys(string $domain): PowerDnsSecKeySet
    {
        return $this->powerDnsClient->getKeys($domain);
    }

    /**
     * Get specific DNSSEC key for zone.
     */
    public function getDnsZoneKey(string $domain): PowerDnsSecKey
    {
        $keys = $this->powerDnsClient->getKeys($domain);

        return $keys->findByType('csk');
    }

    /**
     * Enable DNSSEC for existing zone.
     */
    public function enableDnssec(string $domain): DnsZone
    {
        $zone = $this->powerDnsClient->getZone($domain);

        if ($zone->hasDnsSec()) {
            throw new RuntimeException("DNSSEC for $domain already enabled");
        }

        $zone->dnsSec = true;

        return $this->powerDnsClient->updateZone($zone);
    }

    /**
     * Disable DNSSEC for existing zone.
     *
     *
     * @throws DnsZoneNotFoundException
     * @throws GuzzleException
     * @throws JsonException
     * @throws PdnsResponseException
     */
    public function disableDnssec(string $domain): DnsZone
    {
        $zone = $this->powerDnsClient->getZone($domain);

        if (! $zone->hasDnsSec()) {
            throw new RuntimeException("DNSSEC for $domain already disabled");
        }

        $zone->dnsSec = false;

        return $this->powerDnsClient->updateZone($zone);
    }

    /**
     * @throws GuzzleException
     * @throws PdnsResponseException
     *
     * @return PowerDnsMetadata[]
     */
    public function getMetadata(string $domain): array
    {
        return $this->powerDnsClient->getMetadata($domain);
    }

    /**
     * @param string[] $ip_addresses
     *
     * @throws JsonException
     * @throws GuzzleException
     * @throws PdnsResponseException
     * @throws PdnsValidationException
     */
    public function createMetadata(string $domain, PowerDnsMetadataType $metadata, array $ip_addresses): void
    {
        $this->powerDnsClient->createMetadata($domain, $metadata, $ip_addresses);
    }

    /**
     * @throws JsonException
     * @throws GuzzleException
     * @throws PdnsResponseException
     */
    public function deleteMetadata(string $domain, PowerDnsMetadataType $metadata): void
    {
        $this->powerDnsClient->deleteMetadata($domain, $metadata);
    }

    /**
     * @throws JsonException
     * @throws GuzzleException
     * @throws PdnsResponseException
     */
    public function sendNotify(string $domain): void
    {
        $this->powerDnsClient->sendNotify($domain);
    }

    /**
     * @throws GuzzleException
     * @throws PdnsResponseException
     */
    public function deleteZone(string $domain): void
    {
        $this->powerDnsClient->removeZone($domain);
    }

    /**
     * @throws GuzzleException
     * @throws JsonException
     * @throws PdnsResponseException
     * @throws PdnsValidationException
     */
    public function enablePremiumDns(string $domain): void
    {
        $this->ensureMinimumTTL($domain);

        $ipAddresses = [
            $this->configuration->getAsBoolean('dns.gandi.live_dns_notify_bridge.use_ipv6')
                ? $this->configuration->getAsString('dns.gandi.live_dns_notify_bridge.ipv6')
                : $this->configuration->getAsString('dns.gandi.live_dns_notify_bridge.ipv4'),
        ];

        $this->powerDnsClient->createMetadata(
            $domain,
            PowerDnsMetadataType::ALLOW_AXFR_FROM,
            $ipAddresses,
        );
        $this->powerDnsClient->createMetadata(
            $domain,
            PowerDnsMetadataType::ALSO_NOTIFY,
            $ipAddresses,
        );
        $this->powerDnsClient->createMetadata(
            $domain,
            PowerDnsMetadataType::SOA_EDIT,
            ['INCEPTION-INCREMENT'],
        );
        $this->powerDnsClient->updateLiveDns($domain, true);

        $this->powerDnsClient->sendNotify($domain);

        $this->jobDispatcher->dispatch(new UpdateNameservers($domain, true));
    }

    public function disablePremiumDns(string $domain, bool $shouldUpdateNameservers): void
    {
        $this->powerDnsClient->deleteMetadata($domain, PowerDnsMetadataType::ALLOW_AXFR_FROM);
        $this->powerDnsClient->deleteMetadata($domain, PowerDnsMetadataType::ALSO_NOTIFY);
        $this->powerDnsClient->deleteMetadata($domain, PowerDnsMetadataType::SOA_EDIT);
        $this->powerDnsClient->updateLiveDns($domain, false);

        if ($shouldUpdateNameservers) {
            $this->jobDispatcher->dispatch(new UpdateNameservers($domain, false));
        }
    }

    /**
     * @param mixed[] $data
     *
     * @throws DnsZoneNotFoundException
     * @throws GuzzleException
     * @throws JsonException
     * @throws ValidationException
     */
    public function addRecordFromArray(string $domain, array $data): DnsZone
    {
        return $this->addRecordFromObject($domain, $this->hydrator->hydrate($data));
    }

    /**
     * @throws DnsZoneNotFoundException
     * @throws GuzzleException
     * @throws JsonException
     */
    public function addRecordFromObject(string $domain, DnsRecordInterface $record): DnsZone
    {
        $this->logger->info(
            'Add record to zone',
            [
                LoggingContextKeys::DOMAIN_NAME => $domain,
                LoggingContextKeys::META => [
                    'record' => $record,
                ],
            ],
        );

        return $this->addDnsRecord($domain, $record);
    }

    /**
     * @param mixed[] $data
     *
     * @throws ValidationException
     * @throws DnsZoneNotFoundException
     * @throws PdnsResponseException
     * @throws GuzzleException
     * @throws JsonException
     */
    public function deleteRecordFromArray(string $domain, array $data): DnsZone
    {
        return $this->deleteRecordFromObject($domain, $this->hydrator->hydrate($data));
    }

    /**
     * @throws DnsZoneNotFoundException
     * @throws PdnsResponseException
     * @throws GuzzleException
     * @throws JsonException
     */
    public function deleteRecordFromObject(string $domain, DnsRecordInterface $record): DnsZone
    {
        $this->logger->info(
            'Delete record',
            [
                LoggingContextKeys::DOMAIN_NAME => $domain,
                LoggingContextKeys::META => [
                    'record' => $record,
                ],
            ],
        );

        return $this->removeDnsRecord($domain, $record);
    }

    /**
     * @param mixed[] $old
     * @param mixed[] $new
     *
     * @throws ValidationException
     * @throws DnsZoneNotFoundException
     * @throws JsonException
     * @throws GuzzleException
     * @throws PdnsResponseException
     */
    public function prepareUpdateRecord(string $domain, array $old, array $new): DnsZone
    {
        $change = new ChangedDnsRecord(
            $this->hydrator->hydrate($old, false),
            $this->hydrator->hydrate($new),
        );

        $this->logger->info(
            'Update record',
            [
                LoggingContextKeys::DOMAIN_NAME => $domain,
                LoggingContextKeys::META => [
                    'change' => $change,
                ],
            ],
        );

        return $this->updateRecord($domain, $change);
    }

    /**
     * @throws GuzzleException
     * @throws DnsZoneNotFoundException
     * @throws JsonException
     *
     * @return Collection<int, DnsRecordInterface>
     */
    public function getDnsRecordsForDomain(string $domain): Collection
    {
        $this->logger->info(
            'Fetching records for zone',
            [
                LoggingContextKeys::DOMAIN_NAME => $domain,
            ],
        );

        $zone = $this->getDnsZone($domain);

        return $this->filterModifiableRecords($zone);
    }

    /**
     * @internal Only for use in Ferry/DNS migrations!
     *
     * @param DnsRecordInterface[] $records
     *
     * @throws JsonException
     * @throws PdnsResponseException
     * @throws GuzzleException
     */
    public function cleanupRecordsForPresigning(string $domain, array $records): void
    {
        $this->logger->debug('Cleaning up records for presigning', [
            LoggingContextKeys::DOMAIN_NAME => $domain,
        ]);

        $this->powerDnsClient->cleanupRecordsForPresigning($domain, $records);
    }

    /**
     * @throws GuzzleException
     *
     * See https://apidoc.cldin.eu/#/default/disablepresigned
     */
    public function disablePresignedOnZone(DnsZone $zone): void
    {
        $this->logger->debug('Disable presigned on zone', [
            LoggingContextKeys::DOMAIN_NAME => $zone->getFqdn()->withoutTrailingDot(),
        ]);

        $this->powerDnsClient->disablePresignedOnZone($zone);
    }

    public function changeToMasterAndEmptyMasters(string $domain): void
    {
        $zone = $this->powerDnsClient->getZone($domain);

        $this->powerDnsClient->changeToMasterAndEmptyMasters($zone);
    }

    /**
     * @throws GuzzleException
     * @throws JsonException
     * @throws DnsZoneNotFoundException
     * @throws PdnsResponseException
     */
    public function applyTemplate(?DnsZone $pdnsZone, string $zone, DnsCustomerTemplate $template): DnsZone
    {
        $recordsDeleted = [];
        $recordsCreated = [];

        $dnsZone = $pdnsZone ?? $this->getDnsZone($zone);
        $records = $template->records;

        // Clean up all existing records except SOA & NS before we apply the template.
        foreach ($dnsZone->getRecords() as $record) {
            if ($record->getType() !== 'NS' && $record->getType() !== 'SOA' && $record instanceof AbstractRecord) {
                $dnsZone->removeRecord($record);
                $recordsDeleted[] = $record;
            }
        }

        $records->each(function (DnsCustomerTemplateRecord $record) use (&$dnsZone, $zone, &$recordsCreated): void {
            $dnsRecord = $this->dnsRecordConverter->transformToTypedRecord($record, $zone);
            $dnsZone = $dnsZone->addRecord($dnsRecord);
            $recordsCreated[] = $dnsRecord;
        });

        $changedDnsZone = $this->powerDnsClient->changeZone($dnsZone);

        $domain = $dnsZone->getFqdn()->toNative();

        $this->dnsLogService->logMultipleRecords($recordsDeleted, $domain, DnsChangeType::DELETED);
        $this->dnsLogService->logMultipleRecords($recordsCreated, $domain, DnsChangeType::CREATED);

        return $changedDnsZone;
    }

    /**
     * @throws DnsZoneNotFoundException
     * @throws GuzzleException
     * @throws JsonException
     */
    public function getOrCreateDnsSecKeys(string $domain): PowerDnsSecKeySet
    {
        $dnsZone = $this->getDnsZone($domain);

        if (! $dnsZone->hasDnsSec()) {
            $this->enableDnssec($domain);
        }

        return $this->getDnsZoneKeys($domain);
    }

    /**
     * Filters a DNS zone to a collection of only the modifiable records in a DNS zone.
     *
     * @return Collection<int, DnsRecordInterface>
     */
    public function filterModifiableRecords(DnsZone $dnsZone): Collection
    {
        return new Collection(
            array_filter(
                $dnsZone->getRecords(),
                fn (DnsRecordInterface $dnsRecord): bool => in_array(
                    $dnsRecord->getType(),
                    DnsRecordTypes::getModifiable(),
                    true,
                ),
            ),
        );
    }

    private function removeParkingDnsRecords(string $domain, DnsZone $dnsZone): void
    {
        foreach ($this->dnsZoneService->getParkingAddressRecords($domain) as $parkingRecord) {
            foreach ($dnsZone->getRecordsOfTypeAndName(
                $parkingRecord->getType(),
                $parkingRecord->getName(),
            ) as $existingRecord) {
                $dnsZone->removeRecord($existingRecord);
            }
        }
    }

    /**
     * @return DnsRecordInterface[]
     */
    private function findConflictingParkingRecords(string $domain, DnsZone $dnsZone): array
    {
        $parkingRecords = $this->dnsZoneService->getParkingAddressRecords($domain);

        $conflictingRecords = [];
        $handledNames = [];

        foreach ($parkingRecords as $parkingRecord) {
            $name = $parkingRecord->getType() . '|' . $parkingRecord->getName();
            if (array_key_exists($name, $handledNames)) {
                continue;
            }

            $handledNames[$name] = true;

            $parkingContents = array_values(array_map(
                fn (DnsRecordInterface $record): string => $record->getContent(),
                array_filter(
                    $parkingRecords,
                    fn (DnsRecordInterface $record): bool => (
                        $record->getType() === $parkingRecord->getType()
                        && $record->getName() === $parkingRecord->getName()
                    ),
                ),
            ));

            $liveRecords = $dnsZone->getRecordsOfTypeAndName($parkingRecord->getType(), $parkingRecord->getName());

            $parkingLiveRecords = array_values(array_filter(
                $liveRecords,
                fn (DnsRecordInterface $record): bool => in_array($record->getContent(), $parkingContents, true),
            ));
            $otherLiveRecords = array_filter(
                $liveRecords,
                fn (DnsRecordInterface $record): bool => ! in_array($record->getContent(), $parkingContents, true),
            );

            if ($parkingLiveRecords !== [] && $otherLiveRecords !== []) {
                $conflictingRecords = [...$conflictingRecords, ...$parkingLiveRecords];
            }
        }

        return $conflictingRecords;
    }

    private function ensureMinimumTTL(string $domain): void
    {
        $originalZone = $this->getDnsZone($domain);
        $newZone = clone $originalZone;

        new Collection($originalZone->getRecords())
            ->filter(fn (DnsRecordInterface $record): bool => $record->getTtl() < self::MINIMUM_TTL_PREMIUM_DNS)
            ->each(function (DnsRecordInterface $record) use ($newZone) {
                $newRecord = $this->hydrator->hydrate(
                    ['ttl' => self::MINIMUM_TTL_PREMIUM_DNS] + $record->toArray(),
                    false,
                );

                $newZone->removeRecord($record);
                $newZone->addRecord($newRecord);
            });

        $diff = $originalZone->diff($newZone);

        if ($diff->getChanges() === []) {
            return;
        }

        $this->applyDiffToZone($originalZone, $diff);
    }
}
