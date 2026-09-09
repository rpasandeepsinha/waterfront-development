<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Actions\SSL;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Collection;
use Waterfront\Domain\Ferry\Jobs\TechnicalSslMigrationJob;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class ExecuteTechnicalSslMigrationAction
{
    public function __construct(private readonly Dispatcher $jobDispatcher)
    {
    }

    /**
     * @param Collection<int, Subscription> $subscriptions
     */
    public function execute(Collection $subscriptions): void
    {
        foreach ($subscriptions as $subscription) {
            $subscription->technical_status = TechnicalStatus::PENDING->value;
            $subscription->save();

            $this->jobDispatcher->dispatch(new TechnicalSslMigrationJob(
                subscription: $subscription,
                failedTechnicalStatus: TechnicalStatus::FAILED->value
            ));
        }
    }
}
