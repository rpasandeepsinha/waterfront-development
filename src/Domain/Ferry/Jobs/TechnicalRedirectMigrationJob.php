<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Throwable;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\Entities\DnsZone;
use Waterfront\Domain\DNS\Exceptions\DnsZoneNotFoundException;
use Waterfront\Domain\Ferry\Dto\Redirects\RedirectTechnicalPayload;
use Waterfront\Domain\Ferry\Enums\MigrationStep;
use Waterfront\Domain\Ferry\Services\RedirectMigrationService;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Webmozart\Assert\Assert;

class TechnicalRedirectMigrationJob extends MigrationJob implements ShouldQueue
{
    private RedirectMigrationService $redirectMigrationService;

    private DnsService $dnsService;

    public function __construct(
        public Subscription $subscription,
        protected ?string $failedTechnicalStatus,
        protected RedirectTechnicalPayload $redirectTechnicalPayload,
    ) {
        parent::__construct($this->subscription, $this->failedTechnicalStatus);
    }

    public function getMigrationStep(): MigrationStep
    {
        return MigrationStep::REDIRECT_MIGRATION;
    }

    protected function getSuccessfulTechnicalStatus(): string
    {
        return TechnicalStatus::OK->value;
    }

    protected function registerServices(): void
    {
        $this->redirectMigrationService = $this->resolve(RedirectMigrationService::class);
        $this->dnsService = $this->resolve(DnsService::class);
    }

    protected function runMigration(): void
    {
        $domain = $this->subscription->domain;

        Assert::string(
            $domain,
            'There always needs to be a domain present on the subscription with redirect migrations',
        );

        $this->redirectMigrationService->migrateRedirecting(
            zone: $this->fetchDnsZone($domain), // if the zone does not exist only the redirecting database will be provisioned
            subscription: $this->subscription,
            source: $this->redirectTechnicalPayload->source,
            target: $this->redirectTechnicalPayload->destination,
            type: $this->redirectTechnicalPayload->type,
            referenceCustomerId: $this->migratedCustomer->reference_customer_number,
            jobUuid: $this->getJobId(),
        );
    }

    protected function rollback(Throwable $throwable): void
    {
        /*
         * We chose to not add the rollback due to the complexity of rolling back DNS changes
         * if one of the records fail, you'd need to keep track of every change individually otherwise.
         * Instead we can just retry the migration.
         */
    }

    private function fetchDnsZone(string $domain): ?DnsZone
    {
        try {
            $zone = $this->dnsService->getDnsZone($domain);
        } catch (DnsZoneNotFoundException) {
            return null;
        }

        return $zone;
    }
}
