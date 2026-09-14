<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Services;

use ErrorException;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Collection;
use JsonException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\DTO\Nameserver;
use Waterfront\Domain\DNS\Entities\AddedDnsRecord;
use Waterfront\Domain\DNS\Entities\DnsRecords\DefaultRecord;
use Waterfront\Domain\DNS\Entities\DnsZone;
use Waterfront\Domain\DNS\Entities\DnsZoneDiff;
use Waterfront\Domain\DNS\Exceptions\DnsZoneNotFoundException;
use Waterfront\Domain\DNS\Models\DnsNameserver;
use Waterfront\Domain\DNS\Models\DnsTemplate;
use Waterfront\Domain\DNS\Models\DnsTemplateRecordSetRow;
use Waterfront\Domain\DNS\Services\DnsNameserverRetriever;
use Waterfront\Domain\Domains\DomainService;
use Waterfront\Domain\Ferry\Repositories\InternalNamserverRepository;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\PowerDnsClient\Exceptions\PdnsResponseException;
use Waterfront\Support\Enums\LoggingContextKeys;

class DnsMigrationService
{
    public function __construct(
        private readonly ConfigurationInterface $configuration,
        private readonly DnsService $dnsService,
        private readonly DnsNameserverRetriever $dnsNameserverRetriever,
        private readonly DomainService $domainService,
        private readonly NameserverResolver $nameserverResolver,
        private readonly InternalNamserverRepository $internalNamserverRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function isDnssecSupported(string $domain, ProviderSlug $driver): bool
    {
        return $this->domainService->isDnssecSupported($domain, $driver);
    }

    public function resetDnssec(string $domain, ProviderSlug $providerSlug): void
    {
        $this->domainService->disableDnssec($domain, $providerSlug);

        $this->domainService->enableDnssec($domain, $providerSlug);
    }

    public function getDnsZone(Subscription $subscription, string $domain, string $referenceCustomerNumber): ?DnsZone
    {
        try {
            return $this->dnsService->getDnsZone($domain);
        } catch (DnsZoneNotFoundException) {
            return null;
        } catch (Throwable $exception) {
            $this->logger->debug(
                'Unexpected exception while trying to get DNS zone',
                [
                    LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID => $referenceCustomerNumber,
                    LoggingContextKeys::EXCEPTION => $exception,
                ],
            );

            throw $exception;
        }
    }

    public function zoneHasNoRecords(Subscription $subscription, DnsZone $zone, string $referenceCustomerNumber): bool
    {
        $hasNoRecords = $zone->getRecords() === [];

        if ($hasNoRecords) {
            $this->logger->debug(
                'No DNS records in zone',
                [
                    LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                    LoggingContextKeys::DOMAIN_NAME => $zone->getFqdn()->withoutTrailingDot(),
                    LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID => $referenceCustomerNumber,
                    LoggingContextKeys::META => [
                        'zone' => $zone->toArray(),
                    ],
                ],
            );
        }

        $hasNoNSOrSOARecords = $zone->getRecordsOfType('NS') === [] || $zone->getRecordsOfType('SOA') === [];

        if ($hasNoNSOrSOARecords) {
            $this->logger->debug(
                'No NS or SOA records in DNS zone',
                [
                    LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                    LoggingContextKeys::DOMAIN_NAME => $zone->getFqdn()->withoutTrailingDot(),
                    LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID => $referenceCustomerNumber,
                    LoggingContextKeys::META => [
                        'num_of_ns_records' => count($zone->getRecordsOfType('NS')),
                        'num_of_soa_records' => count($zone->getRecordsOfType('SOA')),
                        'zone' => $zone->toArray(),
                    ],
                ],
            );
        }

        return $hasNoRecords;
    }

    public function addDefaultRecords(
        Subscription $subscription,
        DnsZone $zone,
        string $referenceCustomerNumber,
    ): DnsZone {
        $zoneDiff = $this->getDefaultRecordZoneDiff($zone);

        $this->logger->debug(
            'Adding default records to zone',
            [
                LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                LoggingContextKeys::DOMAIN_NAME => $zone->getFqdn()->withoutTrailingDot(),
                LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID => $referenceCustomerNumber,
                LoggingContextKeys::META => [
                    'changes' => $zoneDiff->getChanges(),
                ],
            ],
        );

        return $this->dnsService->applyDiffToZone($zone, $zoneDiff);
    }

    public function hasModernInternalNameservers(
        int $subscriptionId,
        string $domain,
        string $referenceCustomerNumber,
        ProviderSlug $driver,
    ): bool {
        $this->logger->debug(
            'Checking if migration domain has modern internal nameservers',
            [
                LoggingContextKeys::SUBSCRIPTION_ID => $subscriptionId,
                LoggingContextKeys::DOMAIN_NAME => $domain,
                LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID => $referenceCustomerNumber,
            ],
        );

        $nameservers = $this->getRegistryNameservers($domain, $driver);

        if ($nameservers->isEmpty()) {
            return false;
        }

        $modernInternalNameservers = DnsNameserver::pluck('nameserver');

        // Every nameserver must be a modern (2.0 environment) nameserver
        return $nameservers->every(fn (string $nameserver) => $modernInternalNameservers->contains($nameserver));
    }

    public function hasLegacyInternalNameservers(
        int $subscriptionId,
        string $domain,
        string $referenceCustomerNumber,
        ProviderSlug $driver,
    ): bool {
        $this->logger->debug(
            'Checking if migration zone has legacy internal nameservers',
            [
                LoggingContextKeys::SUBSCRIPTION_ID => $subscriptionId,
                LoggingContextKeys::DOMAIN_NAME => $domain,
                LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID => $referenceCustomerNumber,
            ],
        );

        $nameservers = $this->getRegistryNameservers($domain, $driver);

        if ($nameservers->isEmpty()) {
            return false;
        }

        // Every nameserver must be a migratable nameserver
        return $nameservers->every(fn (string $nameserver) => $this->isMigratableNameserver($nameserver));
    }

    public function hasLegacyWhitelabelNameservers(
        int $subscriptionId,
        string $domain,
        string $referenceCustomerNumber,
        ProviderSlug $driver,
    ): bool {
        $this->logger->debug(
            'Checking if migration zone has legacy whitelabel nameservers',
            [
                LoggingContextKeys::SUBSCRIPTION_ID => $subscriptionId,
                LoggingContextKeys::DOMAIN_NAME => $domain,
                LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID => $referenceCustomerNumber,
            ],
        );

        $nameservers = $this->getRegistryNameservers($domain, $driver);

        if ($nameservers->isEmpty()) {
            return false;
        }

        // Every nameserver must be a migratable nameserver
        return $nameservers->every(function (string $nameserver) {
            // Check if whitelabel nameserver via reverse DNS
            $reverseDNSNameservers = $this->getNameserversViaReverseDNS($nameserver);

            if ($reverseDNSNameservers === false) {
                // Couldn't resolve hostname
                return false;
            }

            return array_any($reverseDNSNameservers, fn ($nameserverRDNSHostname) => $this->isMigratableNameserver(
                $nameserverRDNSHostname,
            ));
        });
    }

    /**
     * @param array<int, string> $nameservers
     */
    public function isSetOfNameserversMigratable(array $nameservers): bool
    {
        return array_all($nameservers, fn ($nameserver) => $this->isMigratableNameserver($nameserver));
    }

    public function isMigratableNameserver(string $hostname): bool
    {
        $migratableNameservers = explode(',', $this->configuration->getAsString('ferry-domain.migratable_nameservers'));
        $migratableNameserversRegex = $this->configuration->getAsString('ferry-domain.migratable_nameservers_regex');

        /* @see https://yh-jira.atlassian.net/browse/SWD-9894 */
        $hostname = strtolower($hostname);

        return (
            in_array($hostname, $migratableNameservers, true)
            || preg_match($migratableNameserversRegex, $hostname) > 0
            || $this->internalNamserverRepository->isInternalNamserver($hostname)
        );
    }

    /**
     * @return array<int, string>|false
     */
    public function getNameserversViaReverseDNS(string $hostname): array|false
    {
        try {
            $nameserverIPs = $this->nameserverResolver->getNameserverIPs($hostname);
        } catch (ErrorException $exception) {
            $this->logger->error(
                $exception->getMessage(),
                [
                    LoggingContextKeys::EXCEPTION => $exception,
                ],
            );
            $nameserverIPs = false;
        }

        if ($nameserverIPs === [] || $nameserverIPs === false) {
            $this->logger->warning(
                "Couldn't get IP address(es) for nameserver in DNS migration service",
                [
                    LoggingContextKeys::SERVER_HOSTNAME => $hostname,
                ],
            );

            return false;
        }

        $nameserverRDNSHostnames = [];

        foreach ($nameserverIPs as $nameserverIP) {
            try {
                $nameserverRDNSHostname = $this->nameserverResolver->getNameserverHostname($nameserverIP);
            } catch (ErrorException $exception) {
                $this->logger->error($exception->getMessage());
                $nameserverRDNSHostname = false;
            }

            if ($nameserverRDNSHostname === false) {
                $this->logger->warning(
                    "Couldn't get hostname for nameserver in DNS migration service",
                    [
                        LoggingContextKeys::SERVER_HOSTNAME => $hostname,
                        LoggingContextKeys::META => [
                            'nameserver_ip' => $nameserverIP,
                        ],
                    ],
                );

                return false;
            }

            $nameserverRDNSHostnames[] = $nameserverRDNSHostname;
        }

        return $nameserverRDNSHostnames;
    }

    public function createDnsZone(Subscription $subscription, string $domain, string $referenceCustomerNumber): void
    {
        $collection = $this->dnsNameserverRetriever->retrieve(2);

        /** @var Nameserver[] $nameservers */
        $nameservers = $collection->map(fn (DnsNameserver $dnsNameserver) => new Nameserver($dnsNameserver->nameserver))->toArray();

        $this->logger->debug(
            'Unable to find dns while migrating. Creating empty zone',
            [
                LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
                LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID => $referenceCustomerNumber,
            ],
        );

        $this->dnsService->createDnsZone(
            $domain,
            'default',
            null,
            null,
            false,
            $nameservers,
        );
    }

    /**
     * This function should only be called to set newly migrated DNS zones to Master.
     *
     * @throws DnsZoneNotFoundException
     * @throws GuzzleException
     * @throws JsonException
     * @throws PdnsResponseException
     */
    public function changeToMasterAndEmptyMasters(
        string $domain,
        int $subscriptionId,
        string $migratedCustomerReference,
    ): void {
        $this->logger->debug(
            'Changing zone to kind: MASTER and empty masters reference on zone',
            [
                LoggingContextKeys::SUBSCRIPTION_ID => $subscriptionId,
                LoggingContextKeys::DOMAIN_NAME => $domain,
                LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID => $migratedCustomerReference,
            ],
        );

        $this->dnsService->changeToMasterAndEmptyMasters($domain);
    }

    /**
     * @return Collection<int, string>
     */
    private function getRegistryNameservers(string $domain, ProviderSlug $driver): Collection
    {
        $hostnames = $this->domainService->fetchDomain($domain, $driver)->ns;

        return new Collection($hostnames);
    }

    private function getDefaultRecordZoneDiff(DnsZone $zone): DnsZoneDiff
    {
        /** @var array<int, string> $nameservers */
        $nameservers = $this->dnsNameserverRetriever->retrieve(2)->pluck('nameserver')->toArray();

        $primaryNameserver = $nameservers[0];
        $fallbackNameserver = $nameservers[1];

        /** @var DnsTemplate $defaultTemplate */
        $defaultTemplate = DnsTemplate::query()
            ->with('recordSets.rows.dnsTemplateRecordSet')
            ->where('slug', 'default')
            ->firstOrFail();

        $ns1TemplateRecord = $this->findDnsTemplateRecord(
            dnsTemplate: $defaultTemplate,
            templateRecordType: 'NS',
            whereContent: '{ns1}.',
        );
        $ns2TemplateRecord = $this->findDnsTemplateRecord(
            dnsTemplate: $defaultTemplate,
            templateRecordType: 'NS',
            whereContent: '{ns2}.',
        );
        $soaTemplateRecord = $this->findDnsTemplateRecord(
            dnsTemplate: $defaultTemplate,
            templateRecordType: 'SOA',
            whereContent: '{ns1}.',
        );

        return new DnsZoneDiff([
            new AddedDnsRecord(
                $this->makeDefaultRecordFromTemplateRecord(
                    templateRow: $ns1TemplateRecord,
                    zone: $zone,
                    find: '{ns1}',
                    replace: $primaryNameserver,
                ),
            ),
            new AddedDnsRecord(
                $this->makeDefaultRecordFromTemplateRecord(
                    templateRow: $ns2TemplateRecord,
                    zone: $zone,
                    find: '{ns2}',
                    replace: $fallbackNameserver,
                ),
            ),
            new AddedDnsRecord(
                $this->makeDefaultRecordFromTemplateRecord(
                    templateRow: $soaTemplateRecord,
                    zone: $zone,
                    find: '{ns1}',
                    replace: $primaryNameserver,
                ),
            ),
        ]);
    }

    private function findDnsTemplateRecord(
        DnsTemplate $dnsTemplate,
        string $templateRecordType,
        string $whereContent,
    ): DnsTemplateRecordSetRow {
        $recordSet = $dnsTemplate->recordSets->where('type', $templateRecordType)->first();

        if ($recordSet === null) {
            throw new RuntimeException(sprintf(
                "DNS template record set with type '%s' not found in template '%s'",
                $templateRecordType,
                $dnsTemplate->slug,
            ));
        }

        $recordSetRow = $recordSet->rows->first(
            fn (DnsTemplateRecordSetRow $recordSetRow) => str_contains($recordSetRow->content, $whereContent),
        );

        if ($recordSetRow === null) {
            throw new RuntimeException(sprintf(
                "DNS template record set row for type '%s' with content '%s' not found in template '%s'",
                $templateRecordType,
                $whereContent,
                $dnsTemplate->slug,
            ));
        }

        return $recordSetRow;
    }

    private function makeDefaultRecordFromTemplateRecord(
        DnsTemplateRecordSetRow $templateRow,
        DnsZone $zone,
        string $find,
        string $replace,
    ): DefaultRecord {
        return new DefaultRecord(
            type: $templateRow->dnsTemplateRecordSet->type,
            name: $zone->getFqdn()->toNative(),
            content: str_replace($find, $replace, $templateRow->content),
            ttl: $templateRow->dnsTemplateRecordSet->ttl,
        );
    }
}
