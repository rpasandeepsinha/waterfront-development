<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Services\ManualMigration;

use Carbon\CarbonImmutable;
use Doctrine\Instantiator\Exception\UnexpectedValueException;
use Exception;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Collection;
use Psr\Log\LoggerInterface;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Ferry\Dto\Hosting\HostingMigrationPayload;
use Waterfront\Domain\Ferry\Enums\MigrationSource;
use Waterfront\Domain\Ferry\Enums\MigrationStep;
use Waterfront\Domain\Ferry\Enums\MigrationSubscriptionStatus;
use Waterfront\Domain\Ferry\Jobs\ConfigureDnsMigrationJob;
use Waterfront\Domain\Ferry\Jobs\EnableDnsSecMigrationJob;
use Waterfront\Domain\Ferry\Jobs\ManualMigration\ConfigureDnsDefaultZone;
use Waterfront\Domain\Ferry\Jobs\ManualMigration\ConfigureDnsEmptyZone;
use Waterfront\Domain\Ferry\Jobs\ManualMigration\ConfigureDnsZonePromotion;
use Waterfront\Domain\Ferry\Jobs\ManualMigration\NameserverSetCurrent;
use Waterfront\Domain\Ferry\Jobs\ManualMigration\NameserverSetDefault;
use Waterfront\Domain\Ferry\Jobs\NameserverMigrationJob;
use Waterfront\Domain\Ferry\Jobs\TechnicalDomainMigrationJob;
use Waterfront\Domain\Ferry\Jobs\TechnicalHostingMigrationJob;
use Waterfront\Domain\Ferry\Models\MigratedSubscriptionSteps;
use Waterfront\Domain\Ferry\Repositories\MigratedSubscriptionStepsRepository;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

class ManualTechnicalMigrationsService
{
    public function __construct(
        private readonly Dispatcher $jobDispatcher,
        private readonly MigratedSubscriptionStepsRepository $repository,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param MigrationStep[] $migrationSteps
     *
     * @throws Exception
     */
    public function migrate(Subscription $subscription, array $migrationSteps): void
    {
        foreach ($migrationSteps as $migrationStep) {
            $migrationSubscription = new MigratedSubscriptionSteps();
            $migrationSubscription->status = MigrationSubscriptionStatus::NOT_EXECUTED;
            $migrationSubscription->subscription_id = $subscription->id;
            $migrationSubscription->step = $migrationStep;
            $migrationSubscription->executed_at = null;

            $migrationSubscription->save();
        }
    }

    public function setStepStatus(
        Subscription $subscription,
        MigrationStep $migrationStep,
        MigrationSubscriptionStatus $status,
    ): void {
        $currentStep = $this->repository->findStepBySubscription($subscription, $migrationStep);

        if ($currentStep === null) {
            return;
        }

        $this->logger->info('Updating status for manual migration technical step ', [
            LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
            LoggingContextKeys::CUSTOMER_ID => $subscription->customer_id,
            LoggingContextKeys::META => [
                'manual_migration_step' => $currentStep->step,
                'status' => $status->value,
            ],
        ]);

        $currentStep->status = $status;
        $currentStep->executed_at = CarbonImmutable::now();
        $currentStep->setUpdatedAt(CarbonImmutable::now());
        $currentStep->save();
    }

    /**
     * @param array<int, array<string, mixed>>|null $payload
     *
     * @throws Exception
     */
    public function fireNextStep(Subscription $subscription, ?array $payload): void
    {
        $nextStep = $this->repository->findNextStepForSubscription($subscription);

        if ($nextStep === null) {
            return;
        }

        $this->logger->info('Dispatching next manual migration technical step', [
            LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
            LoggingContextKeys::CUSTOMER_ID => $subscription->customer_id,
            LoggingContextKeys::META => [
                'manual_migration_step' => $nextStep->step,
            ],
        ]);

        match ($nextStep->step) {
            MigrationStep::NAMESERVER => $this->jobDispatcher->dispatch(
                new NameserverMigrationJob(
                    $subscription,
                    DomainStatus::FAILED->value,
                    MigrationSource::MANUAL_MIGRATION,
                ),
            ),
            MigrationStep::DOMAIN_MIGRATION => $this->jobDispatcher->dispatch(
                new TechnicalDomainMigrationJob(
                    $subscription,
                    DomainStatus::FAILED->value,
                    null,
                    MigrationSource::MANUAL_MIGRATION,
                ),
            ),
            MigrationStep::ENABLE_DNSSEC => $this->jobDispatcher->dispatch(
                new EnableDnsSecMigrationJob(
                    $subscription,
                    DomainStatus::FAILED->value,
                    MigrationSource::MANUAL_MIGRATION,
                ),
            ),
            MigrationStep::CONFIGURE_DNS => $this->jobDispatcher->dispatch(
                new ConfigureDnsMigrationJob(
                    $subscription,
                    DomainStatus::FAILED->value,
                    MigrationSource::MANUAL_MIGRATION,
                ),
            ),
            MigrationStep::CONFIGURE_DNS_DEFAULT_ZONE => $this->jobDispatcher->dispatch(
                new ConfigureDnsDefaultZone($subscription),
            ),
            MigrationStep::CONFIGURE_DNS_EMPTY_ZONE => $this->jobDispatcher->dispatch(
                new ConfigureDnsEmptyZone($subscription),
            ),
            MigrationStep::CONFIGURE_DNS_ZONE_PROMOTION => $this->jobDispatcher->dispatch(
                new ConfigureDnsZonePromotion($subscription),
            ),
            MigrationStep::NAMESERVER_SET_CURRENT => $this->jobDispatcher->dispatch(
                new NameserverSetCurrent($subscription),
            ),
            MigrationStep::NAMESERVER_SET_DEFAULT => $this->jobDispatcher->dispatch(
                new NameserverSetDefault($subscription),
            ),
            MigrationStep::HOSTING_MIGRATION => $this->dispatchHostingMigration($subscription, $payload),
            MigrationStep::SITEBUILDER_MIGRATION,
            MigrationStep::MAIL_ONLY_MIGRATION,
            MigrationStep::REDIRECT_MIGRATION,
            MigrationStep::SSL_MIGRATION,
            MigrationStep::CUSTOMER,
            MigrationStep::RESELLER_HOSTING_MIGRATION,
            MigrationStep::BACKUP_MIGRATION,
            MigrationStep::SUBSCRIPTION,
                => throw new UnexpectedValueException('To be implemented'),
        };
    }

    /**
     * @param ?array<mixed> $payload
     */
    private function dispatchHostingMigration(Subscription $subscription, ?array $payload): void
    {
        $collection = new Collection();
        Assert::isArray($payload);
        $data = $payload[0];
        Assert::isArray($data);
        Assert::string($data['reference_subscription_id']);
        Assert::string($data['driver']);
        Assert::string($data['hostname']);
        Assert::isArray($data['server_data']);
        /** @var array<string, int|string> $serverData */
        $serverData = $data['server_data'];
        $jobPayload = new HostingMigrationPayload(
            $collection->add($subscription),
            $data['reference_subscription_id'],
            $data['driver'],
            $data['hostname'],
            HostingMigrationPayload::resolveTechnicalDetails($serverData, $data['driver']),
        );
        $this->jobDispatcher->dispatch(
            new TechnicalHostingMigrationJob(
                $subscription,
                TechnicalStatus::FAILED->value,
                $jobPayload,
                migrationSource: MigrationSource::MANUAL_MIGRATION,
            ),
        );
    }
}
