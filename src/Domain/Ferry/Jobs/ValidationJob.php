<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Jobs;

use Carbon\CarbonImmutable;
use Illuminate\Bus\Dispatcher;
use Illuminate\Pipeline\Pipeline;
use Psr\Log\LoggerInterface;

use function resolve as resolveFromContainer;

use Throwable;
use Waterfront\Domain\Ferry\Dto\Validation\ValidationPayload;
use Waterfront\Domain\Ferry\Enums\AzureDataFactoryMessageType;
use Waterfront\Domain\Ferry\Enums\MigrationSource;
use Waterfront\Domain\Ferry\Exceptions\ValidationPipelineException;
use Waterfront\Domain\Ferry\Jobs\AzureDataFactory\AzureDataFactoryJobRequester;
use Waterfront\Domain\Ferry\Jobs\AzureDataFactory\AzureDataFactoryMessage;
use Waterfront\Domain\Ferry\Pipes\ValidationPipeInterface;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;

class ValidationJob extends AbstractQueueableJob
{
    public int $tries = 3;

    /**
     * Increase timeout so Openprovider calls can go through. (they're slow ~20% of the time).
     *
     * Five hours should also give every subscription 60 seconds if a customer has 300 subscriptions.
     */
    public int $timeout = 18000;

    private bool $adfWebhookHasBeenCalled = false;

    /**
     * @param array<int, string> $pipes
     */
    public function __construct(
        protected ValidationPayload $validationPayload,
        protected array $pipes,
        public readonly MigrationSource $migrationSource = MigrationSource::AZURE_DATA_FACTORY,
    ) {
        parent::__construct();
    }

    /**
     * @throws ValidationPipelineException
     * @throws Throwable
     */
    public function handle(Pipeline $pipeline, Dispatcher $dispatcher, LoggerInterface $logger): void
    {
        $timestampStart = CarbonImmutable::now();
        $this->validationPayload->setJobId($this->getJobId());

        $logger->debug(
            'Migration validation pipeline has started',
            [
                LoggingContextKeys::QUEUE_JOB_ID => $this->getJobId(),
                LoggingContextKeys::QUEUE_ATTEMPT => $this->attempts(),
                LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $this->validationPayload->validationReference,
                LoggingContextKeys::MIGRATION_SOURCE => $this->migrationSource->value,
            ]
        );

        foreach ($this->pipes as $pipe) {
            if (! is_subclass_of($pipe, ValidationPipeInterface::class)) {
                throw new ValidationPipelineException(sprintf(
                    'Incompatible pipe [%s] given. A pipe should implement the ValidationPipeInterface.',
                    $pipe,
                ));
            }
        }

        try {
            /** @var ValidationPayload $processedValidationPayload */
            $processedValidationPayload = $pipeline
                ->send($this->validationPayload)
                ->through($this->pipes)
                ->via('handle')
                ->then(fn ($result): ValidationPayload => $result);
        } catch (Throwable $exception) {
            $logger->error(
                'Migration validation pipeline experienced an exception',
                [
                    LoggingContextKeys::QUEUE_JOB_ID => $this->getJobId(),
                    LoggingContextKeys::QUEUE_ATTEMPT => $this->attempts(),
                    LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $this->validationPayload->validationReference,
                    LoggingContextKeys::EXCEPTION => $exception,
                ]
            );

            if ($this->migrationSource === MigrationSource::AZURE_DATA_FACTORY) {
                // No matter what the case is there always needs to be a webhook sent back to ADF.
                // Here we simply take any exception and force send it to adf and re-trow
                // the exception.
                $this->callADFValidationWebhook($dispatcher, $this->validationPayload->validationReference, [
                    'message'   => 'Uncaught exception occurred in the validation pipelines. Please contact Ferry development',
                    'exception' => $exception->getMessage(),
                ]);
            }

            throw $exception;
        }

        $logger->debug(
            'Migration validation pipeline for reference: {reference} has been completed. Sending results to ADF',
            [
                LoggingContextKeys::QUEUE_JOB_ID => $this->getJobId(),
                LoggingContextKeys::QUEUE_ATTEMPT => $this->attempts(),
                LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $processedValidationPayload->validationReference,
                LoggingContextKeys::MIGRATION_SOURCE => $this->migrationSource->value,
                LoggingContextKeys::META => [
                    'results' => $processedValidationPayload->validationResults,
                ],
            ]
        );

        $this->callADFValidationWebhook($dispatcher, $this->validationPayload->validationReference, $processedValidationPayload->validationResults);

        $logger->debug(
            'Migration validation job has been completed',
            [
                LoggingContextKeys::QUEUE_JOB_ID => $this->getJobId(),
                LoggingContextKeys::QUEUE_ATTEMPT => $this->attempts(),
                LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $processedValidationPayload->validationReference,
                LoggingContextKeys::MIGRATION_SOURCE => $this->migrationSource->value,
            ]
        );

        $this->reportValidationMetaData(
            start: $timestampStart,
            ended: CarbonImmutable::now(),
            logger: $logger,
            validationReference: $this->validationPayload->validationReference
        );

        $this->delete();
    }

    public function failed(?Throwable $exception): void
    {
        // Not using the ones from handle since we have no guarantee that the handle function
        // has been called at this point.
        //
        // We have only a guarantee that the constructor has been run.
        $dispatcher = self::resolve(Dispatcher::class);
        $logger = self::resolve(LoggerInterface::class);

        if ($this->adfWebhookHasBeenCalled && $this->migrationSource === MigrationSource::AZURE_DATA_FACTORY) {
            $logger->debug(
                'ADF webhook has already been called.',
                [
                    LoggingContextKeys::QUEUE_JOB_ID => $this->getJobId(),
                    LoggingContextKeys::QUEUE_ATTEMPT => $this->attempts(),
                    LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $this->validationPayload->validationReference,
                    LoggingContextKeys::EXCEPTION => $exception,
                ]
            );

            return;
        }

        $logger->error(
            'Job itself failed when running a ferry validation job.',
            [
                LoggingContextKeys::QUEUE_JOB_ID => $this->getJobId(),
                LoggingContextKeys::QUEUE_ATTEMPT => $this->attempts(),
                LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $this->validationPayload->validationReference,
                LoggingContextKeys::EXCEPTION => $exception,
            ]
        );

        if ($this->migrationSource === MigrationSource::AZURE_DATA_FACTORY) {
            $this->callADFValidationWebhook($dispatcher, $this->validationPayload->validationReference, [
                'message'   => 'Job itself failed when running a ferry validation job. Full Exception should be present in the failed jobs table. Please contact Ferry development.',
                'exception' => $exception?->getMessage() ?? 'Exception was null',
            ]);
        }
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags()
    {
        return [
            $this->getQueueName()->value,
            sprintf('ferry-validation referenceCustomerId: %s', $this->validationPayload->validationReference),
        ];
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::FERRY_VALIDATION;
    }

    protected function getJobId(): string
    {
        return $this->job?->getJobId() ?? 'unknown';
    }

    /**
     * WorkAround for the fact that it's not possible to load services into a job constructor.
     *
     * @template T
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    final protected function resolve(string $class): mixed
    {
        return resolveFromContainer($class); // @phpstan-ignore-line
    }

    /**
     * @param array<mixed> $validationResults
     */
    private function callADFValidationWebhook(
        Dispatcher $jobDispatcher,
        string $reference,
        array $validationResults
    ): void {
        if ($this->adfWebhookHasBeenCalled && $this->migrationSource === MigrationSource::AZURE_DATA_FACTORY) {
            return;
        }

        $jobDispatcher->dispatch(
            new AzureDataFactoryJobRequester(
                message: AzureDataFactoryMessage::create(
                    AzureDataFactoryMessageType::VALIDATION_EXECUTED->value,
                    [
                        'reference' => $this->validationPayload->validationReference,
                        'results' => $validationResults,
                        'timeline' => $this->validationPayload->validationTimeline,
                    ]
                ),
                reference: $reference
            )
        );

        $this->adfWebhookHasBeenCalled = true;
    }

    private function reportValidationMetaData(
        CarbonImmutable $start,
        CarbonImmutable $ended,
        LoggerInterface $logger,
        string $validationReference
    ): void {
        $logger->debug(
            'Migration validation metadata',
            [
                LoggingContextKeys::QUEUE_JOB_ID => $this->getJobId(),
                LoggingContextKeys::QUEUE_ATTEMPT => $this->attempts(),
                LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $validationReference,
                LoggingContextKeys::MIGRATION_SOURCE => $this->migrationSource->value,
                LoggingContextKeys::META => [
                    'started_at' => $start->toTimeString(),
                    'ended_at' => $ended->toTimeString(),
                    'runtime_in_seconds' => (int) $start->diffInSeconds($ended, true),
                    'job_id' => $this->getJobId(),
                    'job_uuid' => $this->job?->uuid(),
                    'job_name' => $this->job?->getName(),
                    'connection_name' => $this->job?->getConnectionName(),
                    'queue_name' => $this->job?->getQueue(),
                ],
            ]
        );
    }
}
