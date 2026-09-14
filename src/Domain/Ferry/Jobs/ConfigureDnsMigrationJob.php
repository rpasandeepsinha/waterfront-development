<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Jobs;

use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Contracts\Queue\ShouldQueue;
use JsonException;
use Throwable;
use Waterfront\Domain\DNS\Actions\DisableZonePresigningAction;
use Waterfront\Domain\DNS\Exceptions\DnsZoneNotFoundException;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Ferry\Actions\DNS\UpdateZoneToMasterAction;
use Waterfront\Domain\Ferry\Enums\MigrationStep;
use Waterfront\Domain\Ferry\Exceptions\UnknownProductGroupException;
use Waterfront\Domain\Ferry\Services\DnsMigrationService;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Infra\PowerDnsClient\Exceptions\PdnsResponseException;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

class ConfigureDnsMigrationJob extends MigrationJob implements ShouldQueue
{
    private DisableZonePresigningAction $disableZonePresigningAction;

    private UpdateZoneToMasterAction $updateZoneToMasterAction;

    private DnsMigrationService $dnsMigrationService;

    public function getMigrationStep(): MigrationStep
    {
        return MigrationStep::CONFIGURE_DNS;
    }

    /**
     * @throws DnsZoneNotFoundException
     * @throws PdnsResponseException
     * @throws GuzzleException
     * @throws JsonException
     */
    protected function runMigration(): void
    {
        $subscription = $this->subscription;
        $domain = $subscription->domain;

        Assert::notNull(
            $domain,
            sprintf(
                'Domain for subscription with ID: {%d} was NULL. This is not allowed in DNS Configure Migrations',
                $subscription->id,
            ),
        );

        $zone = $this->dnsMigrationService->getDnsZone(
            $subscription,
            $domain,
            $this->migratedCustomer->reference_customer_number,
        );

        if ($zone === null) {
            $this->dnsMigrationService->createDnsZone(
                $subscription,
                $domain,
                $this->migratedCustomer->reference_customer_number,
            );

            return;
        }

        $this->updateZoneToMasterAction->execute(
            $domain,
            $subscription->id,
            $this->migratedCustomer->reference_customer_number,
        );

        if ($this->dnsMigrationService->zoneHasNoRecords(
            $subscription,
            $zone,
            $this->migratedCustomer->reference_customer_number,
        )) {
            $this->dnsMigrationService->addDefaultRecords(
                $subscription,
                $zone,
                $this->migratedCustomer->reference_customer_number,
            );
        }

        $this->logger->debug(
            'Cleaning up DNSSEC records and calling presigned endpoint',
            [
                LoggingContextKeys::QUEUE_JOB_ID => $this->getJobId(),
                LoggingContextKeys::QUEUE_ATTEMPT => $this->attempts(),
                LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                LoggingContextKeys::DOMAIN_NAME => $domain,
                LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID =>
                    $this->migratedCustomer->reference_customer_number,
            ],
        );

        $this->disableZonePresigningAction->disable($domain);
    }

    protected function getSuccessfulTechnicalStatus(): string
    {
        return match (ProductGroupType::from($this->subscription->product->productGroup->slug->value)) {
            ProductGroupType::EXTENSION => DomainStatus::ACTIVE->value,
            ProductGroupType::DNS => TechnicalStatus::OK->value,
            default => throw new UnknownProductGroupException(
                'Unknown product group type: ' . $this->subscription->product->productGroup->slug->value,
            ),
        };
    }

    protected function rollback(Throwable $throwable): void
    {
        //...
    }

    protected function registerServices(): void
    {
        $this->disableZonePresigningAction = $this->resolve(DisableZonePresigningAction::class);
        $this->updateZoneToMasterAction = $this->resolve(UpdateZoneToMasterAction::class);
        $this->dnsMigrationService = $this->resolve(DnsMigrationService::class);
    }
}
