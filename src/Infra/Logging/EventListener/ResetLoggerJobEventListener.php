<?php

declare(strict_types=1);

namespace Waterfront\Infra\Logging\EventListener;

use Illuminate\Log\Logger;
use Illuminate\Log\LogManager;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobReleasedAfterException;
use Monolog\ResettableInterface;
use Psr\Log\LoggerInterface;

class ResetLoggerJobEventListener
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(JobFailed|JobProcessed|JobExceptionOccurred|JobReleasedAfterException $event): void
    {
        $logger = $this->logger;
        if ($logger instanceof LogManager) {
            $logger = $logger->driver();
        }

        if ($logger instanceof Logger) {
            $logger = $logger->getLogger();
        }

        if ($logger instanceof ResettableInterface) {
            $logger->reset();
        }
    }
}
