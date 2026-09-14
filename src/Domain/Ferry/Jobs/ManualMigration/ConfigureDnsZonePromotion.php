<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Jobs\ManualMigration;

use Psr\Log\LoggerInterface;
use Waterfront\Domain\DNS\Actions\DisableZonePresigningAction;
use Waterfront\Domain\Ferry\Enums\MigrationStep;
use Waterfront\Domain\Ferry\Services\DnsMigrationService;

/**
 * Promotes the slave zone to master in PowerDNS and removes DNSSEC presigning.
 *
 * @see MigrationJobEventListener
 */
class ConfigureDnsZonePromotion extends ManualMigrationJob
{
    public function handle(
        DnsMigrationService $dnsMigrationService,
        DisableZonePresigningAction $disableZonePresigningAction,
        LoggerInterface $logger,
    ): void {
        $domain = $this->subscription->domain;
        assert(is_string($domain));

        $dnsMigrationService->changeToMasterAndEmptyMasters($domain, $this->subscription->id, '');

        $zone = $dnsMigrationService->getDnsZone($this->subscription, $domain, '');
        assert($zone !== null);

        if ($dnsMigrationService->zoneHasNoRecords($this->subscription, $zone, '')) {
            $dnsMigrationService->addDefaultRecords($this->subscription, $zone, '');
        }

        $disableZonePresigningAction->disable($domain);
    }

    public function getMigrationStep(): MigrationStep
    {
        return MigrationStep::CONFIGURE_DNS_ZONE_PROMOTION;
    }
}
