<?php

declare(strict_types=1);

namespace Waterfront\Apps\OneOffScripts\CreateRedirectsFromLegacyDatabase;

use Illuminate\Support\Collection;
use Psr\Log\LoggerInterface;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\Entities\DnsRecords\CnameRecord;
use Waterfront\Domain\DNS\Entities\DnsRecords\DefaultRecord;
use Waterfront\Domain\DNS\Interfaces\DnsRecordInterface;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\Redirects\Models\CaddyContext;
use Waterfront\Infra\Configuration\Configuration;
use Waterfront\Support\Enums\LoggingContextKeys;

class RedirectDnsRecordUpdater
{
    private const int DNS_RECORD_TTL = 1200;

    public bool $dryRun = true;

    private readonly string $legacyIpv4;

    private readonly string $legacyIpv6;

    private readonly string $caddyRedirectIpv4;

    private readonly string $caddyRedirectIpv6;

    private readonly string $aliasOrCnameContent;

    public function __construct(
        private readonly DnsService $dnsService,
        private readonly Configuration $config,
        private readonly LoggerInterface $logger,
    ) {
        $this->legacyIpv4 = $this->config->getAsString('redirects.service.ipv4_host');
        $this->legacyIpv6 = $this->config->getAsString('redirects.service.ipv6_host');

        $this->caddyRedirectIpv4 = $this->config->getAsString('caddyclient.redirect_a');
        $this->caddyRedirectIpv6 = $this->config->getAsString('caddyclient.redirect_aaaa');

        $this->aliasOrCnameContent = $this->config->getAsString('caddyclient.redirect_dns');
    }

    /**
     * Main method for handling the DNS conversion / migration.
     */
    public function updateDnsRecordToCaddy(string $rootDomain, string $source): void
    {
        $this->logger->info(
            sprintf('Updating DNS for redirect with source [%s] and destination [%s]', $source, $rootDomain),
            $this->getLogContext($rootDomain, $source)
        );

        $existingRecords = $this->removeLegacyRedirectDNS($rootDomain, $source);
        $this->createCaddyRedirectDNS($rootDomain, $source, $existingRecords);
    }

    public function updateRedirectDnsFromProvisionDeployment(CaddyContext $caddyContext): void
    {
        $this->logger->info(
            sprintf('Updating DNS records for [%d] redirect deployments for context [%s]', count($caddyContext->redirectDeployments), $caddyContext->context_uuid),
            [
                LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CADDY,
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::REDIRECT,
                LoggingContextKeys::PROVISIONING_CONTEXT => $caddyContext->context_uuid,
                LoggingContextKeys::ONE_OFF_SCRIPT => NovaCreateRedirectsFromLegacyDatabaseAction::SLUG,
                LoggingContextKeys::META => [
                    'dry-run' => $this->dryRun,
                ],
            ]
        );

        foreach ($caddyContext->redirectDeployments as $deployment) {
            $domain = $deployment->destination;
            $source = $deployment->source;

            $this->updateDnsRecordToCaddy(rootDomain: $domain, source: $source);
        }
    }

    /**
     * @param Collection<int, DnsRecordInterface> $existingRecords Records currently in the zone (post legacy-removal).
     */
    private function createCaddyRedirectDNS(string $rootDomain, string $source, Collection $existingRecords): void
    {
        if ($source === $rootDomain) {
            $this->createCaddyRootDomainRecords($rootDomain, $source, $existingRecords);
            return;
        }

        $this->createCaddySubdomainRecord($rootDomain, $source, $existingRecords);
    }

    /**
     * @param Collection<int, DnsRecordInterface> $existingRecords
     */
    private function createCaddyRootDomainRecords(string $rootDomain, string $source, Collection $existingRecords): void
    {
        $existingNonLegacyRecords = $existingRecords->filter(
            fn (DnsRecordInterface $record) => $record->getName() === $source
                && in_array($record->getType(), ['A', 'AAAA', 'ALIAS'], true)
                && $record->getContent() !== $this->legacyIpv4
                && $record->getContent() !== $this->legacyIpv6
                && $record->getContent() !== $this->caddyRedirectIpv4
                && $record->getContent() !== $this->caddyRedirectIpv6
                && $record->getContent() !== $this->aliasOrCnameContent
        );

        if ($existingNonLegacyRecords->isNotEmpty()) {
            $this->logger->warning(
                sprintf('A,AAAA or ALIAS records already exist for [%s], skipping caddy DNS record creation to avoid breaking existing DNS.', $source),
                $this->getLogContext($rootDomain, $source, [
                    'existing_records' => $existingNonLegacyRecords->map(
                        fn (DnsRecordInterface $record) => sprintf('%s %s %s', $record->getName(), $record->getType(), $record->getContent())
                    )->values()->toArray(),
                ])
            );

            return;
        }

        $aliasRecord = new DefaultRecord(
            type: 'ALIAS',
            name: $rootDomain,
            content: $this->aliasOrCnameContent,
            ttl: self::DNS_RECORD_TTL,
            disabled: false
        );

        $this->logger->info(
            sprintf('Redirect is on root domain [%s], Setting ALIAS record for caddy.', $rootDomain),
            $this->getLogContext($rootDomain, $source, ['ALIAS_record' => $aliasRecord->toArray()])
        );

        if (! $this->dryRun) {
            $this->dnsService->addRecordFromObject($rootDomain, $aliasRecord);
        }
    }

    /**
     * @param Collection<int, DnsRecordInterface> $existingRecords
     */
    private function createCaddySubdomainRecord(string $rootDomain, string $source, Collection $existingRecords): void
    {
        $existingCname = $existingRecords->first(
            fn (DnsRecordInterface $record) => $record->getName() === $source
                && ($record->getType() === 'CNAME' || $record->getType() === 'AAAA' || $record->getType() === 'A')
        );

        if ($existingCname !== null) {
            $this->logger->warning(
                sprintf('CNAME record already exists for [%s], skipping caddy DNS record creation to avoid breaking existing DNS.', $source),
                $this->getLogContext($rootDomain, $source, [
                    'existing_record' => sprintf('%s %s %s', $existingCname->getName(), $existingCname->getType(), $existingCname->getContent()),
                ])
            );

            return;
        }

        $cname = new CnameRecord(
            name: $source,
            content: $this->aliasOrCnameContent,
            ttl: self::DNS_RECORD_TTL,
            disabled: false
        );

        $this->logger->info(
            sprintf('Setting CNAME record for subdomain [%s] that points to [%s]', $source, $this->aliasOrCnameContent),
            $this->getLogContext($rootDomain, $source, ['CNAME_record' => $cname->toArray()])
        );

        if (! $this->dryRun) {
            $this->dnsService->addRecordFromObject($rootDomain, $cname);
        }
    }

    /**
     * @return Collection<int, DnsRecordInterface> All records in the zone (for use in subsequent existence checks).
     */
    private function removeLegacyRedirectDNS(string $rootDomain, string $source): Collection
    {
        if (! $this->dnsService->hasDnsZone($rootDomain)) {
            $this->logger->info(
                sprintf('No DNS zone found for [%s], creating new zone.', $rootDomain),
                $this->getLogContext($rootDomain, $source)
            );

            if (! $this->dryRun) {
                $this->dnsService->createDnsZone($rootDomain);
            }

            return new Collection();
        }

        $records = $this->dnsService->getDnsRecordsForDomain($rootDomain);

        $legacyRedirectRecords = $records->filter(fn (DnsRecordInterface $record) => $record instanceof DefaultRecord)
            ->filter(fn (DnsRecordInterface $record) => $record->getName() === $source)
            ->filter(
                fn (DnsRecordInterface $record) =>
                    ($record->getType() === 'A' && $record->getContent() === $this->legacyIpv4)
                    || ($record->getType() === 'AAAA' && $record->getContent() === $this->legacyIpv6)
                    || ($record->getType() === 'A' && $record->getContent() === $this->caddyRedirectIpv4)
                    || ($record->getType() === 'AAAA' && $record->getContent() === $this->caddyRedirectIpv6)
            );

        $this->logger->info(
            sprintf('Deleting [%d] legacy redirect DNS records for domain [%s] and source [%s]', $legacyRedirectRecords->count(), $rootDomain, $source),
            $this->getLogContext($rootDomain, $source, [
                'records' => $legacyRedirectRecords->map(
                    fn (DefaultRecord $record) => sprintf(
                        '%s %s %s',
                        $record->getName(),
                        $record->getType(),
                        $record->getContent()
                    )
                )->toArray(),
            ])
        );

        if (! $this->dryRun) {
            $legacyRedirectRecords->each(function (DefaultRecord $record) use ($rootDomain): void {
                $this->dnsService->deleteRecordFromObject($rootDomain, $record);
            });

            // Make sure to 'refresh' our record after the last deletions.
            $records = $this->dnsService->getDnsRecordsForDomain($rootDomain);
        }

        return $records;
    }

    /**
     * @param mixed[] $meta
     *
     * @return array<string, string|ProvisionProvider|ProvisionType|mixed[]>
     */
    private function getLogContext(string $rootDomain, string $source, array $meta = []): array
    {
        return [
            LoggingContextKeys::DOMAIN_NAME           => $rootDomain,
            LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CADDY,
            LoggingContextKeys::PROVISIONING_TYPE     => ProvisionType::REDIRECT,
            LoggingContextKeys::ONE_OFF_SCRIPT        => NovaCreateRedirectsFromLegacyDatabaseAction::SLUG,
            LoggingContextKeys::META                  => array_merge_recursive([
                'dry-run'              => $this->dryRun,
                'redirect_source'      => $source,
                'redirect_destination' => $rootDomain,
            ], $meta),
        ];
    }
}
