<?php

declare(strict_types=1);

namespace Waterfront\Infra\Logging\EventListener;

use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobTimedOut;
use Psr\Log\LoggerInterface;
use Waterfront\Support\Enums\LoggingContextKeys;

class LogJobEventListener
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(JobFailed|JobProcessed|JobProcessing|JobTimedOut|JobExceptionOccurred $event): void
    {
        if ($event instanceof JobFailed) {
            $this->handleJobFailed($event);

            return;
        }

        if ($event instanceof JobTimedOut) {
            $this->handleJobTimedOut($event);

            return;
        }

        if ($event instanceof JobExceptionOccurred) {
            $this->handleJobExceptionOccurred($event);

            return;
        }

        $jobClassName = $this->getJobClassName($event);
        $status = $event instanceof JobProcessed ? 'processed' : 'processing';
        $logMessage = sprintf('Job name: %s status: %s queue: %s', $jobClassName, $status, $event->job->getQueue());

        $this->logger->info($logMessage, [
            LoggingContextKeys::QUEUE_NAME => $event->job->getQueue(),
            LoggingContextKeys::QUEUE_MESSAGE_NAME => $jobClassName,
            LoggingContextKeys::QUEUE_JOB_ID => $event->job->getJobId(),
            LoggingContextKeys::QUEUE_ATTEMPT => $event->job->attempts(),
        ]);
    }

    private function handleJobFailed(JobFailed $event): void
    {
        $jobClassName = $this->getJobClassName($event);

        $message = sprintf('Error when processing job (%s): %s', $jobClassName, $event->exception->getMessage());
        $this->logger->error($message, [
            LoggingContextKeys::EXCEPTION => $event->exception,
            LoggingContextKeys::QUEUE_NAME => $event->job->getQueue(),
            LoggingContextKeys::QUEUE_MESSAGE_NAME => $jobClassName,
            LoggingContextKeys::QUEUE_JOB_ID => $event->job->getJobId(),
            LoggingContextKeys::QUEUE_ATTEMPT => $event->job->attempts(),
        ]);
    }

    private function handleJobTimedOut(JobTimedOut $event): void
    {
        $jobClassName = $this->getJobClassName($event);

        $message = sprintf('Timeout when processing job (%s)', $jobClassName);
        $this->logger->error($message, [
            LoggingContextKeys::QUEUE_NAME => $event->job->getQueue(),
            LoggingContextKeys::QUEUE_MESSAGE_NAME => $jobClassName,
            LoggingContextKeys::QUEUE_JOB_ID => $event->job->getJobId(),
            LoggingContextKeys::QUEUE_ATTEMPT => $event->job->attempts(),
        ]);
    }

    private function handleJobExceptionOccurred(JobExceptionOccurred $event): void
    {
        $jobClassName = $this->getJobClassName($event);

        $message = sprintf(
            'Exception occurred when processing job (%s): %s',
            $jobClassName,
            $event->exception->getMessage(),
        );
        $this->logger->error($message, [
            LoggingContextKeys::EXCEPTION => $event->exception,
            LoggingContextKeys::QUEUE_NAME => $event->job->getQueue(),
            LoggingContextKeys::QUEUE_MESSAGE_NAME => $jobClassName,
            LoggingContextKeys::QUEUE_JOB_ID => $event->job->getJobId(),
            LoggingContextKeys::QUEUE_ATTEMPT => $event->job->attempts(),
        ]);
    }

    private function getJobClassName(JobFailed|JobProcessed|JobProcessing|JobTimedOut|JobExceptionOccurred $event): string
    {
        $jobClassName = $event->job->resolveName();
        $payload = $event->job->payload();
        if (array_key_exists('displayName', $payload)) {
            $jobClassName = $payload['displayName'];
        }

        return $jobClassName;
    }
}
