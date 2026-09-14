<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Listeners;

use Exception;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Waterfront\Domain\Ferry\Enums\MigrationSource;
use Waterfront\Domain\Ferry\Enums\MigrationSubscriptionStatus;
use Waterfront\Domain\Ferry\Jobs\ManualMigration\ManualMigrationJob;
use Waterfront\Domain\Ferry\Jobs\MigrationJob;
use Waterfront\Domain\Ferry\Services\ManualMigration\ManualTechnicalMigrationsService;

class MigrationJobEventListener
{
    public function __construct(
        private readonly ManualTechnicalMigrationsService $manualMigrationService,
    ) {
    }

    /**
     * @throws Exception
     */
    public function handle(JobProcessed|JobFailed $event): void
    {
        if (
            ! is_subclass_of($event->job->resolveName(), MigrationJob::class)
            && ! is_subclass_of($event->job->resolveName(), ManualMigrationJob::class)
        ) {
            return;
        }

        $payload = $event->job->payload();

        if (! array_key_exists('data', $payload) || ! array_key_exists('command', $payload['data'])) {
            return;
        }

        $job = unserialize($payload['data']['command']);

        if (! $job instanceof MigrationJob && ! $job instanceof ManualMigrationJob) {
            return;
        }

        if ($job instanceof MigrationJob && $job->migrationSource !== MigrationSource::MANUAL_MIGRATION) {
            return;
        }

        $status = $event instanceof JobFailed
            ? MigrationSubscriptionStatus::FAILED
            : MigrationSubscriptionStatus::EXECUTED;
        $this->manualMigrationService->setStepStatus($job->subscription, $job->getMigrationStep(), $status);

        if ($event instanceof JobFailed) {
            return;
        }

        $this->manualMigrationService->fireNextStep($job->subscription, null);
    }
}
