<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Actions\Domains;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Collection;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Ferry\Jobs\TechnicalDomainMigrationJob;
use Waterfront\Domain\Ferry\Mappers\DomainMapper;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class ExecuteTechnicalDomainMigrationAction
{
    private const int DELAY = 1;

    public function __construct(
        private readonly DomainMapper $domainMapper,
        private readonly Dispatcher $jobDispatcher,
    ) {
    }

    /**
     * @param Collection<int, Subscription>    $subscriptions
     * @param array<int, array<string, mixed>> $technicalPayloads
     */
    public function execute(Collection $subscriptions, array $technicalPayloads): void
    {
        foreach ($subscriptions as $subscription) {
            $subscription->technical_status = DomainStatus::MIGRATION_PENDING->value;
            $subscription->save();

            $technicalDomainPayload = $this->domainMapper->mapDomainWithTechnicalPayload(
                $subscription,
                $technicalPayloads
            );

            $job = new TechnicalDomainMigrationJob(
                subscription: $subscription,
                failedTechnicalStatus: DomainStatus::FAILED->value,
                payload: $technicalDomainPayload
            );

            $job->delay(self::DELAY);
            $this->jobDispatcher->dispatch($job);
        }
    }
}
