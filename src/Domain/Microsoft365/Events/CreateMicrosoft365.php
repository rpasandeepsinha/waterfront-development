<?php

declare(strict_types=1);

namespace Waterfront\Domain\Microsoft365\Events;

use Waterfront\Domain\Subscriptions\Models\Subscription;

class CreateMicrosoft365
{
    public readonly Subscription $subscription;

    /**
     * @param array<Subscription> $subscriptions
     */
    public function __construct(
        public readonly array $subscriptions,
    ) {
        assert(count($subscriptions) > 0);

        $this->subscription = $subscriptions[0];
    }
}
