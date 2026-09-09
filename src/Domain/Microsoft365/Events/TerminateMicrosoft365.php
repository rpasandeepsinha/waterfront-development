<?php

declare(strict_types=1);

namespace Waterfront\Domain\Microsoft365\Events;

use Waterfront\Domain\Subscriptions\Models\Subscription;

class TerminateMicrosoft365
{
    public function __construct(
        public readonly Subscription $subscription
    ) {
    }
}
