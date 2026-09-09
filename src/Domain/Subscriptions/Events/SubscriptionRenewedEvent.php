<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Events;

use Waterfront\Domain\Subscriptions\Models\Subscription;

class SubscriptionRenewedEvent
{
    public function __construct(
        public Subscription $subscription,
    ) {
    }
}
