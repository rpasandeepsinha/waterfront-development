<?php

declare(strict_types=1);

namespace Waterfront\Domain\VPS\Jobs;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use JsonException;
use Throwable;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\VPS\DTO\AsynchronousCloudstackResponse;
use Waterfront\Domain\VPS\Enums\JobStatus;
use Waterfront\Domain\VPS\Exceptions\CloudstackException;
use Waterfront\Domain\VPS\Exceptions\CloudstackJobException;
use Waterfront\Domain\VPS\Models\CloudstackJob;
use Waterfront\Domain\VPS\Models\VirtualMachineDeployment;
use Waterfront\Domain\VPS\Services\CloudstackService;
use Waterfront\Infra\CloudStackClient\Exceptions\ClientException;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;

abstract class CloudstackAsyncJob extends AbstractQueueableJob
{
    protected const int RETRY_DELAY_SECONDS = 30;

    protected const string JOB_TYPE = 'DEFAULT';

    public int $tries = 10;

    public function __construct(
        protected VirtualMachineDeployment $deployment,
        protected CloudstackJob $cloudstackJob,
    ) {
        parent::__construct();
    }

    public function failed(Throwable $exception): void
    {
        Log::error(
            sprintf(
                'Cloudstack %s has failed for subscription %s. Message: %s',
                static::JOB_TYPE,
                $this->deployment->subscription_uuid,
                $exception->getMessage()
            ),
            [
                LoggingContextKeys::SUBSCRIPTION_UUID => $this->deployment->subscription_uuid,
                LoggingContextKeys::META => [
                    'cloudstack_job' => $this->cloudstackJob->toArray(),
                ],
                LoggingContextKeys::EXCEPTION => $exception,
            ]
        );

        if ($this->cloudstackJob->status !== JobStatus::FAILED->value) {
            $this->cloudstackJob->status = JobStatus::FAILED->value;
            $this->cloudstackJob->cloudstack_completed = CarbonImmutable::now();
            $this->cloudstackJob->save();
        }

        $this->deployment->update([
            'last_result' => $exception->getMessage(),
            'last_result_received' => CarbonImmutable::now(),
        ]);
    }

    /**
     * @throws ClientException
     * @throws JsonException
     */
    public function handle(CloudstackService $cloudstackService): void
    {
        $baseClient = $cloudstackService->getBaseClientFromSubscription($this->deployment);
        $jobResponse = $cloudstackService->retrieveJobStatus($this->cloudstackJob->job_id, $baseClient);

        if ($jobResponse->status === JobStatus::PENDING->value) {
            $this->handlePending();
            $this->release(static::RETRY_DELAY_SECONDS * $this->attempts());
            return;
        }

        if ($jobResponse->status === JobStatus::FAILED->value) {
            $this->handleFailed($jobResponse);
            return;
        }

        if ($jobResponse->status === JobStatus::SUCCESS->value) {
            $this->handleSuccess($jobResponse);
            return;
        }

        $this->fail(
            new CloudstackException(
                sprintf(
                    'Cloudstack %s job with ID %s has failed for subscription %s. Unknown job status %d',
                    static::JOB_TYPE,
                    $jobResponse->jobId,
                    $this->deployment->subscription_uuid,
                    $jobResponse->status,
                )
            )
        );
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::CLOUDSTACK;
    }

    protected function handlePending(): void
    {
        if ($this->cloudstackJob->status !== JobStatus::PENDING->value) {
            $this->cloudstackJob->status = JobStatus::PENDING->value;
            $this->cloudstackJob->save();
        }

        if ($this->deployment->subscription->technical_status !== TechnicalStatus::PENDING->value) {
            $this->deployment->subscription->technical_status = TechnicalStatus::PENDING->value;
            $this->deployment->subscription->save();
        }
    }

    protected function handleFailed(AsynchronousCloudstackResponse $jobResponse): void
    {
        $failedMessage = sprintf(
            'Cloudstack %s job with ID %d has failed for subscription %s. Cloudstack Error: %s',
            static::JOB_TYPE,
            $jobResponse->jobId,
            $this->deployment->subscription_uuid,
            json_encode($jobResponse->result, JSON_THROW_ON_ERROR)
        );

        Log::error($failedMessage, [
            LoggingContextKeys::SUBSCRIPTION_UUID => $this->deployment->subscription_uuid,
            LoggingContextKeys::META => [
                'cloudstack_job' => $this->cloudstackJob->toArray(),
            ],
        ]);

        $this->fail(new CloudstackJobException($failedMessage));
    }

    protected function handleSuccess(AsynchronousCloudstackResponse $jobResponse): void
    {
        Log::info(
            sprintf(
                'Cloudstack %s job with ID %d and subscription %s has been handled successfully.',
                static::JOB_TYPE,
                $jobResponse->jobId,
                $this->deployment->subscription_uuid,
            )
        );

        if ($this->cloudstackJob->status !== JobStatus::SUCCESS->value) {
            $this->cloudstackJob->status = JobStatus::SUCCESS->value;
            $this->cloudstackJob->cloudstack_completed = CarbonImmutable::now();
            $this->cloudstackJob->save();
        }

        $this->deployment->update([
            'last_result' => sprintf(
                'Cloudstack %s job with ID %d and subscription %s has been handled successfully.',
                static::JOB_TYPE,
                $jobResponse->jobId,
                $this->deployment->subscription_uuid,
            ),
            'last_result_received' => CarbonImmutable::now(),
        ]);
    }
}
