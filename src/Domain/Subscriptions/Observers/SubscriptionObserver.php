<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Observers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class SubscriptionObserver
{
    public function creating(Subscription $subscription): void
    {
        $this->populateStartDate($subscription)->populateNextBillingDate($subscription)->populateEndDate($subscription);
    }

    public function updating(Subscription $subscription): void
    {
        $this->populateEndDate($subscription);
    }

    public function deleting(Subscription $subscription): void
    {
        Log::info(sprintf(
            'Deleting Subscription : %s domain: %s',
            $subscription->uuid,
            $subscription->domain,
        ));

        $subscription
            ->getDeployments()
            ->each(function ($deployment): void {
                $deployment->delete(); // soft-deleted
            });
    }

    private function populateStartDate(Subscription $subscription): self
    {
        /** @var CarbonImmutable|null $startDate */
        $startDate = $subscription->start_date;

        if (! $startDate instanceof CarbonImmutable) {
            $subscription->start_date = CarbonImmutable::today();
        }

        return $this;
    }

    private function populateNextBillingDate(Subscription $subscription): self
    {
        $subscription->next_billing_date ??= $subscription->start_date->addMonths($subscription->billing_period);

        return $this;
    }

    private function populateEndDate(Subscription $subscription): void
    {
        /** @var CarbonImmutable|null $endDate */
        $endDate = $subscription->end_date;

        if (! $endDate instanceof CarbonImmutable) {
            $subscription->end_date = $subscription->start_date->addMonths($subscription->contract_period);
        }
    }
}
