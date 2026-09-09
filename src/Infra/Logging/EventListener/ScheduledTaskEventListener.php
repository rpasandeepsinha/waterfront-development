<?php

declare(strict_types=1);

namespace Waterfront\Infra\Logging\EventListener;

use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Psr\Log\LoggerInterface;

class ScheduledTaskEventListener
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function handle(ScheduledTaskStarting|ScheduledTaskFinished|ScheduledTaskFailed $event): void
    {
        $status = match ($event::class) {
            ScheduledTaskStarting::class => 'starting',
            ScheduledTaskFinished::class => 'finished',
            ScheduledTaskFailed::class => 'failed',
            default => 'unknown',
        };
        $message = sprintf('Scheduled task %s: %s', $status, $event->task->command);

        $this->logger->debug($message);
    }
}
