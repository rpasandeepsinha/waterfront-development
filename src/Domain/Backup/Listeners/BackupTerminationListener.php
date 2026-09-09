<?php

declare(strict_types=1);

namespace Waterfront\Domain\Backup\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Waterfront\Domain\Backup\Events\BackupTerminateEvent;
use Waterfront\Domain\Backup\Services\BackupService;
use Waterfront\Support\Enums\QueueName;

class BackupTerminationListener implements ShouldQueue
{
    public string $queue = QueueName::DEFAULT->value;

    public int $timeout = 300;

    public function __construct(
        private readonly BackupService $backupService,
    ) {
    }

    public function handle(BackupTerminateEvent $event): void
    {
        $this->backupService->terminate(
            subscription: $event->subscription
        );
    }
}
