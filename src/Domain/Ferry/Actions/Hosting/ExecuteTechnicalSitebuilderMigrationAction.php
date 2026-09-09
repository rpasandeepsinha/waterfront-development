<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Actions\Hosting;

use Illuminate\Contracts\Bus\Dispatcher;
use Waterfront\Domain\Ferry\Dto\Hosting\SitebuilderBundleMigrationPayload;
use Waterfront\Domain\Ferry\Jobs\TechnicalSitebuilderMigrationJob;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;

class ExecuteTechnicalSitebuilderMigrationAction
{
    public function __construct(
        private readonly Dispatcher $jobDispatcher,
    ) {
    }

    /**
     * @param array<int, SitebuilderBundleMigrationPayload> $technicalPayloads
     */
    public function execute(array $technicalPayloads): void
    {
        foreach ($technicalPayloads as $technicalPayload) {
            foreach ($technicalPayload->subscriptions as $subscription) {
                $subscription->technical_status = TechnicalStatus::PENDING->value;
                $subscription->save();

                $this->jobDispatcher->dispatch(
                    new TechnicalSitebuilderMigrationJob(
                        subscription: $subscription,
                        failedTechnicalStatus: TechnicalStatus::FAILED->value,
                        payload: $technicalPayload,
                    )
                );
            }
        }
    }
}
