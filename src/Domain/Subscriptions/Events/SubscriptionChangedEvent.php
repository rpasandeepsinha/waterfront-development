<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Events;

use Waterfront\Domain\Subscriptions\Enums\ProductChangeType;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class SubscriptionChangedEvent
{
    public function __construct(
        public Subscription $subscription,
        public int $charge,
        public ProductChangeType $changeType,
    ) {
    }
}
