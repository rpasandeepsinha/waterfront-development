<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Actions\Backups;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Database\Eloquent\Collection;
use Waterfront\Domain\Ferry\Jobs\TechnicalBackupMigrationJob;
use Waterfront\Domain\Ferry\Mappers\BackupMapper;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class ExecuteTechnicalBackupMigrationAction
{
    public function __construct(
        private readonly Dispatcher $jobDispatcher,
        private readonly BackupMapper $backupMapper,
    ) {
    }

    /**
     * @param array<string, mixed>          $technicalPayloads
     * @param Collection<int, Subscription> $subscriptions
     */
    public function execute(Collection $subscriptions, array $technicalPayloads): void
    {
        foreach ($subscriptions as $subscription) {
            $subscription->technical_status = TechnicalStatus::PENDING->value;
            $subscription->save();

            $mappedPayload = $this->backupMapper->mapSubscriptionsWithBackups($subscription, $technicalPayloads);

            $this->jobDispatcher->dispatch(new TechnicalBackupMigrationJob(
                subscription: $subscription,
                failedTechnicalStatus: TechnicalStatus::FAILED->value,
                backupTechnicalPayload: $mappedPayload,
            ));
        }
    }
}
