<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Actions\DNS;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Collection;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Ferry\Jobs\ConfigureDnsMigrationJob;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class ExecuteConfigureDnsAction
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
            $subscription->technical_status = DomainStatus::MIGRATION_PENDING->value;
            $subscription->save();

            $this->jobDispatcher->dispatch(new ConfigureDnsMigrationJob(
                subscription: $subscription,
                failedTechnicalStatus: DomainStatus::FAILED->value
            ));
        }
    }
}
