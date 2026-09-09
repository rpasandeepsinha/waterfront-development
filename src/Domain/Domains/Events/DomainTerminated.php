<?php

declare(strict_types=1);

namespace Waterfront\Domain\Domains\Events;

use Waterfront\Domain\Subscriptions\Models\Subscription;

class DomainTerminated
{
    public function __construct(
        private readonly Subscription $subscription,
    ) {
    }

    public function getSubscription(): Subscription
    {
        return $this->subscription;
    }
}
