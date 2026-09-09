<?php

declare(strict_types=1);

namespace Waterfront\Domain\ManualProvisioning\Events;

use Illuminate\Queue\SerializesModels;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class DispatchTerminateManualProvisioning
{
    use SerializesModels;

    public function __construct(
        public readonly Subscription $subscription,
    ) {
    }
}
