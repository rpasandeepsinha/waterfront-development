<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Actions\Hosting;

use Illuminate\Contracts\Bus\Dispatcher;
use Waterfront\Domain\Ferry\Dto\Hosting\HostingMigrationPayload as ResellerHostingMigrationPayload;
use Waterfront\Domain\Ferry\Jobs\TechnicalResellerHostingMigrationJob;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;

class ExecuteTechnicalResellerHostingMigrationAction
{
    private const int DELAY = 1;

    public function __construct(
        private readonly Dispatcher $jobDispatcher,
    ) {
    }

    /**
     * @param array<int, ResellerHostingMigrationPayload> $resellerHostingMigrationPayloads
     */
    public function execute(array $resellerHostingMigrationPayloads): void
    {
        foreach ($resellerHostingMigrationPayloads as $resellerHostingMigrationPayload) {
            foreach ($resellerHostingMigrationPayload->subscriptions as $subscription) {
                $subscription->technical_status = TechnicalStatus::PENDING->value;
                $subscription->save();

                $job = new TechnicalResellerHostingMigrationJob(
                    subscription: $subscription,
                    failedTechnicalStatus: TechnicalStatus::FAILED->value,
                    payload: $resellerHostingMigrationPayload,
                );

                $job->delay(self::DELAY);
                $this->jobDispatcher->dispatch($job);
            }
        }
    }
}
