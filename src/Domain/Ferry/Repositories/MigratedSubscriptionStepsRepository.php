<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Waterfront\Domain\Ferry\Enums\MigrationStep;
use Waterfront\Domain\Ferry\Enums\MigrationSubscriptionStatus;
use Waterfront\Domain\Ferry\Models\MigratedSubscriptionSteps;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class MigratedSubscriptionStepsRepository
{
    public function findStepBySubscription(Subscription $subscription, MigrationStep $migrationStep): ?MigratedSubscriptionSteps
    {
        return MigratedSubscriptionSteps::where('subscription_id', $subscription->id)
            ->where('step', $migrationStep)
            ->first();
    }

    public function findNextStepForSubscription(Subscription $subscription): ?MigratedSubscriptionSteps
    {
        return MigratedSubscriptionSteps::where('subscription_id', $subscription->id)
            ->where('status', MigrationSubscriptionStatus::NOT_EXECUTED->value)
            ->orderBy('id')
            ->first();
    }

    /**
     * @return Collection<int, MigratedSubscriptionSteps>
     */
    public function findAllStepsForSubscription(Subscription $subscription): Collection
    {
        return MigratedSubscriptionSteps::where('subscription_id', $subscription->id)->orderBy('id')->get();
    }
}
