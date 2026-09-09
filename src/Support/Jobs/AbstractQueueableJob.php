<?php

declare(strict_types=1);

namespace Waterfront\Support\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use LogicException;
use Waterfront\Support\Enums\QueueName;

abstract class AbstractQueueableJob implements ShouldQueue
{
    use Queueable;
    use InteractsWithQueue;
    use SerializesModels;

    public int $tries = 1;

    public function __construct()
    {
        $this->onQueue($this->getQueueName()->value);
    }

    abstract protected function getQueueName(): QueueName;

    /**
     * Get the backoff delay based on the current attempt.
     * This can be used when you want to manually release
     * the job while still respecting the backoff delay.
     */
    protected function getBackoffDelay(): int
    {
        if (! method_exists($this, 'backoff')) {
            throw new LogicException('The job must implement the backoff method to use the backoff delay');
        }

        $backoff = $this->backoff();
        $attempts = $this->attempts();

        // If the current attempt exceeds the backoff array, use the last value as delay
        return $backoff[$attempts - 1] ?? end($backoff);
    }
}
