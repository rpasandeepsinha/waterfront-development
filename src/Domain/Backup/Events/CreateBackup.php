<?php

declare(strict_types=1);

namespace Waterfront\Domain\Backup\Events;

use Waterfront\Domain\Provision\Backup\Requests\CreateBackupRequest;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class CreateBackup
{
    public function __construct(
        public readonly Subscription $subscription,
        public readonly CreateBackupRequest $createBackupRequest,
    ) {
    }
}
