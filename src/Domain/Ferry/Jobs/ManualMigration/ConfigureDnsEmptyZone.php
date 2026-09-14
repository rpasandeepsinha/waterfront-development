<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Jobs\ManualMigration;

use RuntimeException;
use Waterfront\Domain\Ferry\Enums\MigrationStep;
use Waterfront\Domain\Ferry\Services\DnsMigrationService;

/**
 * Creates an empty PowerDNS zone for the domain.
 *
 * @see MigrationJobEventListener
 */
class ConfigureDnsEmptyZone extends ManualMigrationJob
{
    public function handle(DnsMigrationService $dnsMigrationService): void
    {
        $domain = $this->subscription->domain;
        assert(is_string($domain));

        $zone = $dnsMigrationService->getDnsZone($this->subscription, $domain, '');

        if ($zone !== null) {
            throw new RuntimeException(sprintf('Existing DNS zone found for domain: %s', $domain));
        }

        $dnsMigrationService->createDnsZone($this->subscription, $domain, '');
    }

    public function getMigrationStep(): MigrationStep
    {
        return MigrationStep::CONFIGURE_DNS_EMPTY_ZONE;
    }
}
