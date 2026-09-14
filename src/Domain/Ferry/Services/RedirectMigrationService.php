<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Services;

use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Collection;
use JsonException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\Entities\DnsZone;
use Waterfront\Domain\DNS\Enums\DnsRecordType;
use Waterfront\Domain\DNS\Exceptions\DnsZoneNotFoundException;
use Waterfront\Domain\DNS\Interfaces\DnsRecordInterface;
use Waterfront\Domain\Provision\Redirects\Enums\RedirectType;
use Waterfront\Domain\Redirects\Enums\DnsRedirectProvisionOption;
use Waterfront\Domain\Redirects\Services\RedirectDnsService;
use Waterfront\Domain\Redirects\Services\RedirectService;
use Waterfront\Domain\Servers\Models\LegacyRedirectingServer;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\PowerDnsClient\Exceptions\PdnsResponseException;
use Waterfront\Support\Enums\LoggingContextKeys;

class RedirectMigrationService
{
    public function __construct(
        private readonly RedirectService $redirectService,
        private readonly DnsService $dnsService,
        private readonly LoggerInterface $logger,
        private readonly RedirectDnsService $redirectDnsService,
        private readonly ConfigurationInterface $config,
    ) {
    }

    /**
     * @throws JsonException
     * @throws DnsZoneNotFoundException
     * @throws PdnsResponseException
     * @throws GuzzleException
     */
    public function migrateRedirecting(
        ?DnsZone $zone,
        Subscription $subscription,
        string $source,
        string $target,
        string $type,
        string $referenceCustomerId,
        string $jobUuid,
    ): void {
        $alreadyProvisioned = $this->redirectAlreadyExists($subscription, $source);

        $this->logger->debug('Redirecting database find or add', [
            LoggingContextKeys::QUEUE_JOB_ID => $jobUuid,
            LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID => $referenceCustomerId,
            LoggingContextKeys::META => [
                'redirecting_already_provisioned' => $alreadyProvisioned,
                'source' => $source,
                'target' => $target,
                'type' => $type,
                'zone_is_null' => $zone === null,
            ],
        ]);

        if (! $alreadyProvisioned) {
            try {
                $result = $this->redirectService->createRedirect(
                    subscription: $subscription,
                    domain: $source,
                    destination: $target,
                    redirectType: RedirectType::from($type),
                );

                if ($result->failed) {
                    throw new RuntimeException(
                        message: $result->exception?->getMessage() ?? 'Failed to provision redirect',
                        previous: $result->exception,
                    );
                }
            } catch (Throwable $exception) { // @phpstan-ignore-line There is no case that if we cant add the record we want to touch dns.
                $this->logger->error('Redirecting database error. Unable to add record', [
                    LoggingContextKeys::QUEUE_JOB_ID => $jobUuid,
                    LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID => $referenceCustomerId,
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::META => [
                        'source' => $source,
                        'target' => $target,
                        'type' => $type,
                    ],
                ]);

                return;
            }
        }

        if ($zone === null) {
            return;
        }

        $recordsToUpdate = $this->getLegacyRedirectRecordsOnSource($zone, $source);

        $this->logger->debug('Redirecting records to update', [
            LoggingContextKeys::QUEUE_JOB_ID => $jobUuid,
            LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID => $referenceCustomerId,
            LoggingContextKeys::DOMAIN_NAME => $zone->getFqdn()->withoutTrailingDot(),
            LoggingContextKeys::META => [
                'source' => $source,
                'target' => $target,
                'recordCount' => $recordsToUpdate->count(),
            ],
        ]);

        try {
            if ($recordsToUpdate->isNotEmpty()) {
                // This removes any DNS records that are pointing to existing redirects (legacy and new).
                $this->redirectDnsService->cleanupDnsRecords(
                    domain: $zone->getFqdn()->withoutTrailingDot(),
                    source: $source,
                );

                /*
                 * This will provision the new DNS records ONLY if we do not find any conflicting records.
                 * A customer could have changed its records on legacy portals even though the redirect
                 * was still active. We do not want to break the existing DNS so we ignore these here.
                 */
                $this->redirectDnsService->provisionDnsRecords(
                    domain: $zone->getFqdn()->withoutTrailingDot(),
                    source: $source,
                    dnsProvisionOption: DnsRedirectProvisionOption::IGNORE,
                );
            }
        } catch (Throwable $exception) {
            $this->logger->error('Error when setting up redirect DNS record. Rolling back dns.', [
                LoggingContextKeys::QUEUE_JOB_ID => $jobUuid,
                LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID => $referenceCustomerId,
                LoggingContextKeys::EXCEPTION => $exception,
                LoggingContextKeys::META => [
                    'fqdn' => $zone->getFqdn(),
                    'redirecting_already_provisioned' => $alreadyProvisioned,
                    'source' => $source,
                    'target' => $target,
                    'type' => $type,
                ],
            ]);

            try {
                $currentZone = $this->dnsService->getDnsZone($zone->getFqdn()->withoutTrailingDot());
                $this->dnsService->applyDiffToZone($zone, $currentZone->diff($zone));
            } catch (Throwable $exceptionDuringRollback) {
                $this->logger->error('Error when trying to rollback DNS after failed migration.', [
                    LoggingContextKeys::QUEUE_JOB_ID => $jobUuid,
                    LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID => $referenceCustomerId,
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::META => [
                        'fqdn' => $zone->getFqdn(),
                        'redirecting_already_provisioned' => $alreadyProvisioned,
                        'source' => $source,
                        'target' => $target,
                        'type' => $type,
                    ],
                ]);

                throw $exceptionDuringRollback;
            }

            throw $exception;
        }
    }

    public function redirectAlreadyExists(Subscription $subscription, string $source): bool
    {
        $redirects = $this->redirectService->listRedirects($subscription);

        return array_any($redirects, fn ($redirect) => $redirect['source'] === $source);
    }

    /**
     * @return Collection<int, DnsRecordInterface>
     */
    private function getLegacyRedirectRecordsOnSource(DnsZone $zone, string $source): Collection
    {
        return $this->filterLegacyRedirectDnsRecords($zone)->filter(
            fn (DnsRecordInterface $record) => $record->getName() === $source,
        );
    }

    /**
     * @return Collection<int, DnsRecordInterface>
     */
    private function filterLegacyRedirectDnsRecords(DnsZone $zone): Collection
    {
        /** @var Collection<int, DnsRecordInterface> $records */
        $records = new Collection($zone->getRecords());
        $legacyIPAddresses = LegacyRedirectingServer::all();
        $legacyIPv4Addresses = $legacyIPAddresses->pluck('ipv4')->whereNotNull();
        $legacyIPv6Addresses = $legacyIPAddresses->pluck('ipv6')->whereNotNull();

        $configLegacyIpv4 = $this->config->getAsString('redirects.service.ipv4_host');
        $configLegacyIpv6 = $this->config->getAsString('redirects.service.ipv6_host');

        $legacyIPv4Addresses->add($configLegacyIpv4);
        $legacyIPv6Addresses->add($configLegacyIpv6);

        return $records->filter(
            fn (DnsRecordInterface $record) => (
                $record->getType() === DnsRecordType::A->value
                && $legacyIPv4Addresses->contains($record->getContent())
                || $record->getType() === DnsRecordType::AAAA->value
                && $legacyIPv6Addresses->contains($record->getContent())
            ),
        );
    }
}
