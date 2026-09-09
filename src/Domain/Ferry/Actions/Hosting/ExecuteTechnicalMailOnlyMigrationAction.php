<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Actions\Hosting;

use Illuminate\Contracts\Bus\Dispatcher;
use Waterfront\Domain\Ferry\Dto\Hosting\HostingMigrationPayload;
use Waterfront\Domain\Ferry\Jobs\TechnicalMailOnlyMigrationJob;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;

class ExecuteTechnicalMailOnlyMigrationAction
{
    private const int DELAY = 1;

    public function __construct(
        private readonly Dispatcher $jobDispatcher,
    ) {
    }

    /**
     * @param array<int, HostingMigrationPayload> $hostingMigrationPayloads
     */
    public function execute(array $hostingMigrationPayloads): void
    {
        foreach ($hostingMigrationPayloads as $hostingMigrationPayload) {
            foreach ($hostingMigrationPayload->subscriptions as $subscription) {
                $subscription->technical_status = TechnicalStatus::PENDING->value;
                $subscription->save();

                $job = new TechnicalMailOnlyMigrationJob(
                    subscription: $subscription,
                    failedTechnicalStatus: TechnicalStatus::FAILED->value,
                    payload: $hostingMigrationPayload
                );

                $job->delay(self::DELAY);
                $this->jobDispatcher->dispatch($job);
            }
        }
    }
}
