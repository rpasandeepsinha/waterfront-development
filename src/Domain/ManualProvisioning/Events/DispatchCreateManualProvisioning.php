<?php

declare(strict_types=1);

namespace Waterfront\Domain\ManualProvisioning\Events;

use Waterfront\Domain\Subscriptions\Models\Subscription;

class DispatchCreateManualProvisioning
{
    public function __construct(
        public readonly Subscription $subscription,
    ) {
    }
}
