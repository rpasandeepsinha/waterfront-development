<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Repositories;

use Waterfront\Domain\Subscriptions\Models\CancellationFlowStep;

class CancellationFlowRepository
{
    public function findCancellationFlowStepBySubscriptionId(int $subscriptionId): ?CancellationFlowStep
    {
        return CancellationFlowStep::query()
            ->join('cancellation_flows_subscriptions', 'cancellation_flow_id', '=', 'cancellation_flows_id')
            ->where('cancellation_flows_subscriptions.subscription_id', $subscriptionId)
            ->where('type', '=', 'reasons')
            ->whereNotNull('completed_at')
            ->orderByDesc('completed_at')
            ->first();
    }
}
