<?php

declare(strict_types=1);

namespace Waterfront\Domain\Redirects\Services;

use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use JsonException;
use Psr\Log\LoggerInterface;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\Entities\DnsRecords\DefaultRecord;
use Waterfront\Domain\DNS\Enums\DnsRecordType;
use Waterfront\Domain\DNS\Exceptions\DnsZoneNotFoundException;
use Waterfront\Domain\DNS\Interfaces\DnsRecordInterface;
use Waterfront\Domain\DNS\Services\DnsZoneService;
use Waterfront\Domain\Redirects\Enums\DnsRedirectProvisionOption;
use Waterfront\Domain\Redirects\Exceptions\DnsNeedsRootDomain;
use Waterfront\Domain\Servers\Models\LegacyRedirectingServer;
use Waterfront\Infra\Common\PublicSuffixList;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\PowerDnsClient\Exceptions\PdnsResponseException;
use Waterfront\Support\Enums\LoggingContextKeys;

class RedirectDnsService implements RedirectDnsServiceInterface
{
    public const RECORD_TTL = 1200;

    public const array REDIRECT_RECORD_TYPES = [
        DnsRecordType::A->value,
        DnsRecordType::AAAA->value,
        DnsRecordType::CNAME->value,
        DnsRecordType::ALIAS->value,
    ];

    private const string WILDCARD_RECORD_PREFIX = '*.';

    /**
     * @var EloquentCollection<int, LegacyRedirectingServer>|null
     */
    private ?EloquentCollection $legacyRedirectingServers = null;

    private readonly string $legacyIpv4;

    private readonly string $legacyIpv6;

    private readonly string $caddyRedirectIpv4;

    private readonly string $caddyRedirectIpv6;

    private readonly string $cnameOrAliasContent;

    public function __construct(
        private readonly DnsService $dnsService,
        private readonly PublicSuffixList $publicSuffixList,
        private readonly LoggerInterface $logger,
        private readonly ConfigurationInterface $config,
        private readonly DnsZoneService $dnsZoneService,
    ) {
        $this->cnameOrAliasContent = $this->config->getAsString('caddyclient.redirect_dns');

        $this->legacyIpv4 = $this->config->getAsString('redirects.service.ipv4_host');
        $this->legacyIpv6 = $this->config->getAsString('redirects.service.ipv6_host');

        $this->caddyRedirectIpv4 = $this->config->getAsString('caddyclient.redirect_a');
        $this->caddyRedirectIpv6 = $this->config->getAsString('caddyclient.redirect_aaaa');
    }

    /**
     * @throws JsonException
     * @throws PdnsResponseException
     * @throws GuzzleException
     * @throws DnsNeedsRootDomain
     */
    public function provisionDnsRecords(string $domain, string $source, DnsRedirectProvisionOption $dnsProvisionOption): void
    {
        if (! $this->publicSuffixList->isRootDomain($domain)) {
            throw new DnsNeedsRootDomain($domain);
        }

        $this->logger->info(
            "Provisioning target [{$source}] on [{$domain}] with DNS records that point towards the redirect servers.",
            [
                LoggingContextKeys::DOMAIN_NAME => $source,
                LoggingContextKeys::META        => [
                    'zone' => $domain,
                    'redirect_dns' => $this->cnameOrAliasContent,
                ],
            ]
        );

        try {
            $records = $this->dnsService->getDnsRecordsForDomain($domain);
        } catch (DnsZoneNotFoundException) {
            $this->logger->debug(
                "Zone [{$domain}] was not found. Creating zone for redirect records",
                [
                    LoggingContextKeys::DOMAIN_NAME => $source,
                    LoggingContextKeys::META        => [
                        'zone' => $domain,
                    ],
                ]
            );

            $zone = $this->dnsService->createDnsZone($domain);
            $records = $this->dnsService->filterModifiableRecords($zone);
        }

        $this->removeParkingDnsRecords($domain, $source);

        $existingRecordConflicts = $this->getConflictingRecords(source: $source, existingRecords: $records);

        if ($existingRecordConflicts->isEmpty()) {
            $this->addRedirectDnsRecord($domain, $source);
            return;
        }

        if ($dnsProvisionOption === DnsRedirectProvisionOption::IGNORE) {
            $this->logger->warning(
                "Found existing records that conflict with the redirect record for [{$source}] on zone [{$domain}]. Skipping provisioning of redirect record due to provision option set to IGNORE.",
                [
                    LoggingContextKeys::DOMAIN_NAME => $source,
                    LoggingContextKeys::META        => [
                        'zone' => $domain,
                        'conflicting_records' => $existingRecordConflicts->toArray(),
                    ],
                ]
            );
            return;
        }

        $this->deleteDnsRecords($domain, $source, $existingRecordConflicts);
        $this->addRedirectDnsRecord($domain, $source);
    }

    /**
     * @throws DnsZoneNotFoundException
     * @throws PdnsResponseException
     * @throws GuzzleException
     * @throws JsonException
     */
    public function cleanupDnsRecords(string $domain, string $source): void
    {
        $this->logger->info(
            "Cleaning up records that point towards the redirecting service on [{$source}] for [{$domain}].",
            [
                LoggingContextKeys::DOMAIN_NAME => $source,
                LoggingContextKeys::META        => [
                    'zone' => $domain,
                ],
            ]
        );
        try {
            $records = $this->dnsService->getDnsRecordsForDomain($domain);
        } catch (DnsZoneNotFoundException) {
            $this->logger->info(
                "Zone [{$domain}] was not found.",
                [
                    LoggingContextKeys::DOMAIN_NAME => $source,
                    LoggingContextKeys::META        => [
                        'zone' => $domain,
                    ],
                ]
            );
            return;
        }

        $this->deleteOnlyRedirectsServerDnsRecords($domain, $source, $records);
    }

    /*
     * We should only delete records that are pointing to an existing or old redirect service.
     * Otherwise we break existing DNS for customers. This means that we need to check what
     * content counts as 'redirect' per type and only deletes the ones that match those.
     *
     * Currently, we can have 4 different DNS record types that could be potential redirects.
     * The A and AAAA types derive from the legacy systems and contain a legacy IP address
     * that points to those servers. The ALIAS and CNAME are the newest redirect record.
     */
    public function isRedirectManagedRecord(DnsRecordInterface $record): bool
    {
        return match ($record->getType()) {
            DnsRecordType::A->value => in_array($record->getContent(), $this->getIpv4RedirectValues(), true),
            DnsRecordType::AAAA->value => in_array($record->getContent(), $this->getIpv6RedirectValues(), true),
            DnsRecordType::CNAME->value,
            DnsRecordType::ALIAS->value => rtrim($record->getContent(), '.') === rtrim($this->cnameOrAliasContent, '.'),
            default => false,
        };
    }

    private function removeParkingDnsRecords(string $domain, string $source): void
    {
        $parkingDnsRecords = new Collection($this->dnsZoneService->getParkingAddressRecords($domain))
            ->filter(fn (DnsRecordInterface $record): bool => $this->isParkingRecordForSource($record, $source))
            ->values();

        if ($parkingDnsRecords->isEmpty()) {
            return;
        }

        $this->logger->debug(
            "Removing parking DNS records for [{$source}] on zone [{$domain}]",
            [
                LoggingContextKeys::DOMAIN_NAME => $source,
                LoggingContextKeys::META        => [
                    'zone' => $domain,
                    'parking_dns_records' => $parkingDnsRecords->map(fn (DnsRecordInterface $record): array => $record->toArray())->toArray(),
                ],
            ]
        );

        try {
            $this->deleteDnsRecords($domain, $source, $parkingDnsRecords);
        } catch (DnsZoneNotFoundException|PdnsResponseException|GuzzleException|JsonException) {
            $this->logger->warning(
                "Could not delete parking DNS records for [{$source}] on zone [{$domain}]",
                [
                    LoggingContextKeys::DOMAIN_NAME => $source,
                    LoggingContextKeys::META        => [
                        'zone' => $domain,
                        'parking_dns_records' => $parkingDnsRecords->map(fn (DnsRecordInterface $record): array => $record->toArray())->toArray(),
                    ],
                ]
            );
        }
    }

    /**
     * A parking record applies to the redirect target when it carries the exact name of the source,
     * or when it is a wildcard record that also answers for the source, e.g. *.example.com for sub.example.com.
     */
    private function isParkingRecordForSource(DnsRecordInterface $record, string $source): bool
    {
        $recordName = rtrim($record->getName(), '.');
        $source = rtrim($source, '.');

        if ($recordName === $source) {
            return true;
        }

        if (! str_starts_with($recordName, self::WILDCARD_RECORD_PREFIX)) {
            return false;
        }

        return str_ends_with($source, substr($recordName, strlen(self::WILDCARD_RECORD_PREFIX) - 1));
    }

    /**
     * @param Collection<int, DnsRecordInterface> $existingRecords
     *
     * @return Collection<int, DnsRecordInterface>
     */
    private function getConflictingRecords(string $source, Collection $existingRecords): Collection
    {
        $conflictingTypes = ['A', 'AAAA', 'CNAME'];

        if ($this->publicSuffixList->isRootDomain($source)) {
            $conflictingTypes = ['ALIAS'];
        }

        $conflictingRecords = clone $existingRecords;

        return $conflictingRecords->filter(
            fn (DnsRecordInterface $record) =>
                $record->getName() === $source
                && in_array($record->getType(), $conflictingTypes, true)
        );
    }

    private function addRedirectDnsRecord(string $domain, string $source): void
    {
        $recordType = $this->publicSuffixList->isRootDomain($source)
            ? DnsRecordType::ALIAS
            : DnsRecordType::CNAME;

        $record = new DefaultRecord($recordType->value, $source, $this->cnameOrAliasContent, self::RECORD_TTL);

        $this->logger->info(
            "Setting {$recordType->value} record on zone {$domain}.",
            [
                LoggingContextKeys::DOMAIN_NAME => $domain,
                LoggingContextKeys::META        => [
                    'record' => $record->toArray(),
                ],
            ]
        );

        $this->dnsService->addRecordFromObject(
            $domain,
            $record
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
    private function deleteDnsRecords(string $domain, string $source, Collection $records): void
    {
        $records->each(function (DnsRecordInterface $record) use ($domain): void {
            $this->logger->debug("Provision deleting record {$record->getType()} [{$record->getName()}] [{$record->getContent()}]");
            $this->dnsService->deleteRecordFromObject($domain, $record);
        });

        $this->logger->info(
            "Found {$records->count()} pre-existing redirect records for zone {$domain}. Cleaned up.",
            [
                LoggingContextKeys::DOMAIN_NAME => $source,
                LoggingContextKeys::META => [
                    'zone' => $domain,
                    'filtered_records' => $records->toArray(),
                ],
            ]
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
    private function deleteOnlyRedirectsServerDnsRecords(string $domain, string $source, Collection $records): void
    {
        $filteredRecords = $records
            ->filter(fn (DnsRecordInterface $record) => rtrim($record->getName(), '.') === rtrim($source, '.'))
            ->filter(fn (DnsRecordInterface $record) => $this->isRedirectManagedRecord($record))
            ->each(function (DnsRecordInterface $record) use ($domain): void {
                $this->logger->debug("Deleting legacy redirect record {$record->getType()} [{$record->getName()}] [{$record->getContent()}]");
                $this->dnsService->deleteRecordFromObject($domain, $record);
            });

        $this->logger->info(
            "Found {$filteredRecords->count()} pre-existing redirect records for zone {$domain}. Cleaned up.",
            [
                LoggingContextKeys::DOMAIN_NAME => $source,
                LoggingContextKeys::META => [
                    'zone' => $domain,
                    'filtered_records' => $filteredRecords->toArray(),
                ],
            ]
        );
    }

    /**
     * @return string[]
     */
    private function getIpv4RedirectValues(): array
    {
        // cache legacy servers from DB in object to prevent multiple queries on repeating calls.
        $this->legacyRedirectingServers ??= LegacyRedirectingServer::all();

        /** @var string[] $ipAddresses */
        $ipAddresses = $this->legacyRedirectingServers
            ->pluck('ipv4')
            ->push($this->caddyRedirectIpv4, $this->legacyIpv4)
            ->unique()
            ->toArray();

        return $ipAddresses;
    }

    /**
     * @return string[]
     */
    private function getIpv6RedirectValues(): array
    {
        // cache legacy servers from DB in object to prevent multiple queries on repeating calls.
        $this->legacyRedirectingServers ??= LegacyRedirectingServer::all();

        /** @var string[] $ipAddresses */
        $ipAddresses = $this->legacyRedirectingServers
            ->pluck('ipv6')
            ->filter() // ipv6 is nullable on the model.
            ->push($this->caddyRedirectIpv6, $this->legacyIpv6)
            ->unique()
            ->toArray();

        return $ipAddresses;
    }
}
