<?php

declare(strict_types=1);

namespace Waterfront\Domain\Backup\Events;

use Waterfront\Domain\Subscriptions\Models\Subscription;

class BackupTerminateEvent
{
    public function __construct(
        public Subscription $subscription,
    ) {
    }
}
