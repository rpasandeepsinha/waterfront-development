<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Actions;

use Carbon\CarbonImmutable;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Webmozart\Assert\Assert;

class DetermineExpirationPathForSubscriptionAction
{
    public function __construct(
        private readonly GracefullyExpireSubscriptionAction $gracefullyExpireSubscriptionAction,
    ) {
    }

    public function execute(Subscription $subscription): void
    {
        Assert::null($subscription->parent_subscription_id, 'Only parent subscription is allowed');

        // If the parent subscription is cancelled and the end date is passed, cancel all children
        // Otherwise, check each child whether they are expired
        if ($this->shouldBeExpired($subscription)) {
            foreach ($subscription->children as $child) {
                $this->gracefullyExpireSubscriptionAction->execute($child);
            }

            $this->gracefullyExpireSubscriptionAction->execute($subscription);
            return;
        }

        $this->expireChildSubscriptions($subscription);
    }

    private function expireChildSubscriptions(Subscription $subscription): void
    {
        foreach ($subscription->children as $child) {
            if ($this->shouldBeExpired($child)) {
                $this->gracefullyExpireSubscriptionAction->execute($child);
            }
        }
    }

    private function shouldBeExpired(Subscription $subscription): bool
    {
        return $subscription->administrative_status === AdministrativeStatus::CANCELED->value && $subscription->end_date <= new CarbonImmutable();
    }
}
