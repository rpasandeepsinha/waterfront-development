<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionChangeStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Models\SubscriptionChange;

class SubscriptionChangeRepository
{
    /** @return Collection<int, SubscriptionChange> */
    public function getOpenSubscriptionChangeRequest(Subscription $subscription): Collection
    {
        return SubscriptionChange::query()
            ->where('subscription_uuid', $subscription->uuid)
            ->whereNull('completed_at')
            ->whereIn('status', [SubscriptionChangeStatus::REQUESTED, SubscriptionChangeStatus::EXECUTION_FAILED])
            ->orderBy('requested_at', 'asc')
            ->get();
    }
}
