<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Repositories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Models\SubscriptionMutation;

class SubscriptionMutationRepository
{
    public function findOpenMutation(Subscription $subscription): ?SubscriptionMutation
    {
        return SubscriptionMutation::where('subscription_id', $subscription->id)
            ->whereNull('mutated_at')
            ->orderBy('created_at', 'desc')
            ->first();
    }

    /**
     * @return Collection<int, SubscriptionMutation>
     */
    public function getEligibleForTechnicalProcessing(): Collection
    {
        return SubscriptionMutation::query()->with('subscription')->where('process_technical_at', '<=', CarbonImmutable::today())->whereNull('processed_technical_at')->get();
    }
}
