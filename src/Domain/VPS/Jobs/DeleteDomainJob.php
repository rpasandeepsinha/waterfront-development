<?php

declare(strict_types=1);

namespace Waterfront\Domain\VPS\Jobs;

use Illuminate\Container\Container;
use JsonException;
use Psr\Log\LoggerInterface;
use Throwable;
use Waterfront\Domain\VPS\Enums\JobStatus;
use Waterfront\Domain\VPS\Exceptions\ClientFactoryException;
use Waterfront\Domain\VPS\Exceptions\CloudstackException;
use Waterfront\Domain\VPS\Interfaces\ClientFactoryInterface;
use Waterfront\Domain\VPS\Models\CloudstackJob;
use Waterfront\Domain\VPS\Models\ManagerDomainDeployment;
use Waterfront\Domain\VPS\Services\CloudstackService;
use Waterfront\Infra\CloudStackClient\Exceptions\ClientException;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;

class DeleteDomainJob extends AbstractQueueableJob
{
    protected const int RETRY_DELAY_SECONDS = 300;

    public int $tries = 3;

    public function __construct(
        protected ManagerDomainDeployment $managerDomainDeployment,
        protected CloudstackJob $cloudstackJob,
    ) {
        parent::__construct();
    }

    public function failed(Throwable $exception): void
    {
        $container = Container::getInstance();
        $logger = $container->make(LoggerInterface::class);
        $logger->error(
            sprintf(
                'Error DeleteDomainJob with domain_id {%s} failed after {%d} attempts',
                $this->managerDomainDeployment->domain_id,
                $this->attempts(),
            ),
            [
                LoggingContextKeys::QUEUE_ATTEMPT => $this->attempts(),
                LoggingContextKeys::EXCEPTION => $exception,
                LoggingContextKeys::META => [
                    'managerDomainDeployment' => $this->managerDomainDeployment->id,
                ],
            ]
        );
    }

    /**
     * @throws ClientFactoryException
     * @throws JsonException
     * @throws ClientException
     */
    public function handle(CloudstackService $cloudstackService, ClientFactoryInterface $clientFactory, LoggerInterface $logger): void
    {
        $baseClient = $clientFactory->create($this->managerDomainDeployment)->getBaseClient();
        $jobResponse = $cloudstackService->retrieveJobStatus($this->cloudstackJob->job_id, $baseClient);

        if ($jobResponse->status === JobStatus::PENDING->value) {
            $this->release(static::RETRY_DELAY_SECONDS * $this->attempts());
            return;
        }

        if ($jobResponse->status === JobStatus::FAILED->value) {
            $this->fail(
                new CloudstackException(
                    sprintf(
                        'DeleteDomainJob with ID %d has failed to delete domain {%s}. Cloudstack Error: %s',
                        $jobResponse->jobId,
                        $this->managerDomainDeployment->domain_id,
                        json_encode($jobResponse->result, JSON_THROW_ON_ERROR)
                    )
                )
            );
            return;
        }

        if ($jobResponse->status === JobStatus::SUCCESS->value) {
            $logger->info(
                sprintf(
                    'Cloudstack domain id {%s} successfully deleted',
                    $this->managerDomainDeployment->domain_id,
                ),
                [
                    LoggingContextKeys::META => [
                        'managerDomainDeployment' => $this->managerDomainDeployment->id,
                    ],
                ]
            );
            $this->managerDomainDeployment->delete();
            return;
        }

        $this->fail(
            new CloudstackException(
                sprintf(
                    'DeleteDomainJob with ID %d has failed to delete domain {%s}. Unknown job status %d',
                    $jobResponse->jobId,
                    $this->managerDomainDeployment->domain_id,
                    $jobResponse->status,
                )
            )
        );
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::CLOUDSTACK;
    }
}
