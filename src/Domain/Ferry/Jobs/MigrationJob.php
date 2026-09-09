<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Jobs;

use Carbon\CarbonImmutable;
use Illuminate\Container\Container;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Psr\Log\LoggerInterface;
use Throwable;
use Waterfront\Domain\Customers\Models\MigratedCustomer;
use Waterfront\Domain\Ferry\Enums\AzureDataFactoryMessageType;
use Waterfront\Domain\Ferry\Enums\MigrationSource;
use Waterfront\Domain\Ferry\Enums\MigrationStep;
use Waterfront\Domain\Ferry\Jobs\AzureDataFactory\AzureDataFactoryJobRequester;
use Waterfront\Domain\Ferry\Jobs\AzureDataFactory\AzureDataFactoryMessage;
use Waterfront\Domain\Ferry\Services\AdfPayloadService;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;

abstract class MigrationJob extends AbstractQueueableJob implements ShouldBeUnique
{
    public int $tries = 2;

    public int $backoff = 120;

    /**
     * Increase timeout so Openprovider or other slow 3rd parties
     * calls can go through. (they're slow ~20% of the time).
     */
    public int $timeout = 180;

    public int $uniqueFor = 60;

    protected LoggerInterface $logger;

    protected MigratedCustomer $migratedCustomer;

    private bool $adfWebhookHasBeenCalled = false;

    public function __construct(
        public Subscription $subscription,
        protected string|null $failedTechnicalStatus,
        public MigrationSource $migrationSource = MigrationSource::AZURE_DATA_FACTORY,
    ) {
        parent::__construct();

        $this->migratedCustomer = $this->getMigratedCustomer($this->subscription);
    }

    public function handle(
        AdfPayloadService $adfPayloadService,
        Dispatcher $jobDispatcher,
        LoggerInterface $logger,
    ): void {
        $this->logger = $logger;
        $this->registerServices();

        $messageType      = AzureDataFactoryMessageType::MIGRATION_EXECUTED->value;
        $exceptionPayload = [];

        $migrationStep = $this->getMigrationStep();

        try {
            $this->logger->debug('Starting technical migration for:', [
                LoggingContextKeys::QUEUE_JOB_ID => $this->getJobId(),
                LoggingContextKeys::QUEUE_ATTEMPT => $this->attempts(),
                LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID => $this->migratedCustomer->reference_customer_number,
                LoggingContextKeys::SUBSCRIPTION_ID => $this->subscription->id,
                LoggingContextKeys::DOMAIN_NAME => $this->subscription->domain,
                LoggingContextKeys::MIGRATION_STEP => $migrationStep->value,
                LoggingContextKeys::MIGRATION_SOURCE => $this->migrationSource->value,
                LoggingContextKeys::META => [
                    'timestamp' => CarbonImmutable::now()->toString(),
                    'attempts' => $this->attempts(),
                ],
            ]);

            $this->runMigration();

            $this->subscription->technical_status = $this->getSuccessfulTechnicalStatus();
            $this->subscription->save();

            $this->logger->debug('Finished technical migration for:', [
                LoggingContextKeys::QUEUE_JOB_ID => $this->getJobId(),
                LoggingContextKeys::QUEUE_ATTEMPT => $this->attempts(),
                LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID => $this->migratedCustomer->reference_customer_number,
                LoggingContextKeys::DOMAIN_NAME => $this->subscription->domain,
                LoggingContextKeys::SUBSCRIPTION_ID => $this->subscription->id,
                LoggingContextKeys::MIGRATION_STEP => $migrationStep->value,
                LoggingContextKeys::MIGRATION_SOURCE => $this->migrationSource->value,
                LoggingContextKeys::META => [
                    'timestamp' => CarbonImmutable::now()->toString(),
                    'attempts' => $this->attempts(),
                ],
            ]);
        } catch (Throwable $exception) {
            $this->subscription->technical_status = $this->failedTechnicalStatus;
            $this->subscription->save();

            $messageType = AzureDataFactoryMessageType::MIGRATION_EXECUTED_UNSUCCESSFUL->value;
            $exceptionPayload = ['error' => $exception->__toString()];

            $this->logger->error('Encountered exception during technical migration for:', [
                LoggingContextKeys::QUEUE_JOB_ID => $this->getJobId(),
                LoggingContextKeys::QUEUE_ATTEMPT => $this->attempts(),
                LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID => $this->migratedCustomer->reference_customer_number,
                LoggingContextKeys::DOMAIN_NAME => $this->subscription->domain,
                LoggingContextKeys::SUBSCRIPTION_ID => $this->subscription->id,
                LoggingContextKeys::MIGRATION_STEP => $migrationStep->value,
                LoggingContextKeys::MIGRATION_SOURCE => $this->migrationSource->value,
                LoggingContextKeys::EXCEPTION => $exception,
                LoggingContextKeys::META => [
                    'timestamp' => CarbonImmutable::now()->toString(),
                    'attempts' => $this->attempts(),
                ],
            ]);

            $this->logger->debug('Beginning rollback');

            $this->rollback($exception);

            $this->logger->debug('Rollback complete');

            throw $exception;
        } finally {
            if ($this->migrationSource === MigrationSource::AZURE_DATA_FACTORY) {
                $this->logger->debug('Calling ADF webhook:', [
                    LoggingContextKeys::QUEUE_JOB_ID => $this->getJobId(),
                    LoggingContextKeys::QUEUE_ATTEMPT => $this->attempts(),
                    LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID => $this->migratedCustomer->reference_customer_number,
                    LoggingContextKeys::DOMAIN_NAME => $this->subscription->domain,
                    LoggingContextKeys::SUBSCRIPTION_ID => $this->subscription->id,
                    LoggingContextKeys::MIGRATION_STEP => $this->getMigrationStep()->value,
                    LoggingContextKeys::META => [
                        'timestamp' => CarbonImmutable::now()->toString(),
                        'message_type' => $messageType,
                        'attempts' => $this->attempts(),
                    ],
                ]);

                $this->callADFWebhook(
                    jobDispatcher: $jobDispatcher,
                    subscription: $this->subscription,
                    adfPayloadService: $adfPayloadService,
                    migrationStep: $this->getMigrationStep(),
                    messageType: $messageType,
                    exceptionPayload: $exceptionPayload,
                    reference: $this->migratedCustomer->reference_customer_number
                );

                $this->logger->debug('Finished ADF webhook:', [
                    LoggingContextKeys::QUEUE_JOB_ID => $this->getJobId(),
                    LoggingContextKeys::QUEUE_ATTEMPT => $this->attempts(),
                    LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID => $this->migratedCustomer->reference_customer_number,
                    LoggingContextKeys::DOMAIN_NAME => $this->subscription->domain,
                    LoggingContextKeys::SUBSCRIPTION_ID => $this->subscription->id,
                    LoggingContextKeys::MIGRATION_STEP => $this->getMigrationStep()->value,
                    LoggingContextKeys::META => [
                        'timestamp' => CarbonImmutable::now()->toString(),
                        'message_type' => $messageType,
                        'attempts' => $this->attempts(),
                    ],
                ]);
            }
        }
    }

    public function failed(?Throwable $exception): void
    {
        if ($this->subscription->technical_status !== $this->failedTechnicalStatus) {
            // To prevent model events from running twice
            $this->subscription->technical_status = $this->failedTechnicalStatus;
            $this->subscription->save();
        }

        // Not using the ones from handle since we have no guarantee that the handle function
        // has been called at this point.
        //
        // We have only a guarantee that the constructor has been run.
        $dispatcher = self::resolve(Dispatcher::class);
        $adfPayloadService = self::resolve(AdfPayloadService::class);
        $logger = self::resolve(LoggerInterface::class);

        if ($this->adfWebhookHasBeenCalled && $this->migrationSource === MigrationSource::AZURE_DATA_FACTORY) {
            $logger->debug(
                'ADF webhook has already been called.',
                [
                    LoggingContextKeys::QUEUE_JOB_ID => $this->getJobId(),
                    LoggingContextKeys::QUEUE_ATTEMPT => $this->attempts(),
                    LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID => $this->migratedCustomer->reference_customer_number,
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::META => [
                        'attempts' => $this->attempts(),
                    ],
                ]
            );

            return;
        }

        $logger->error(
            'Job itself failed when running a ferry migration job.',
            [
                LoggingContextKeys::QUEUE_JOB_ID => $this->getJobId(),
                LoggingContextKeys::QUEUE_ATTEMPT => $this->attempts(),
                LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID => $this->migratedCustomer->reference_customer_number,
                LoggingContextKeys::MIGRATION_SOURCE => $this->migrationSource->value,
                LoggingContextKeys::EXCEPTION => $exception,
                LoggingContextKeys::META => [
                    'attempts' => $this->attempts(),
                ],
            ]
        );

        if ($this->migrationSource === MigrationSource::AZURE_DATA_FACTORY) {
            $messageType = AzureDataFactoryMessageType::MIGRATION_EXECUTED_UNSUCCESSFUL->value;
            $exceptionPayload = ['error' => $exception !== null ? $exception->__toString() : 'Exception was null'];

            $this->callADFWebhook(
                jobDispatcher: $dispatcher,
                subscription: $this->subscription,
                adfPayloadService: $adfPayloadService,
                migrationStep: $this->getMigrationStep(),
                messageType: $messageType,
                exceptionPayload: $exceptionPayload,
                reference: $this->migratedCustomer->reference_customer_number
            );
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
            sprintf('%s SubscriptionId: %d', $this->getMigrationStep()->value, $this->subscription->id),
        ];
    }

    public function uniqueId(): string
    {
        return sprintf('%s SubscriptionId: %d', $this->getMigrationStep()->value, $this->subscription->id);
    }

    /**
     * The type of migration step that is currently being run as an Enum.
     * We need this value to fetch the needed information for ADF
     * regarding the current subscription we are migrating.
     */
    abstract public function getMigrationStep(): MigrationStep;

    /**
     * The desired end technical status for this technical migration block.
     * For example with domains it is ACT and for hosting it is ok.
     */
    abstract protected function getSuccessfulTechnicalStatus(): string;

    /**
     * Register all the services needed in the concrete job implementations since
     * it is not possible to pass services though the job constructors. You are not able
     * to serialize closures.
     *
     * Please use the resolve method to get your needed services in your job implementation.
     */
    abstract protected function registerServices(): void;

    /**
     * Should contain the business logic needed to run this technical migration step.
     *
     * To implement new technical migrations don't forget to make your additions to the
     * AdfPayloadService and the MigrateableType Enum.
     */
    abstract protected function runMigration(): void;

    /**
     * Rollback the database changes made inside the runMigration function.
     * Each concrete technical job is responsible for its own rollback process.
     *
     * The base class will handle the technical state of the subscription.
     */
    abstract protected function rollback(Throwable $throwable): void;

    /**
     * @param array<string, mixed> $exceptionPayload
     */
    protected function callADFWebhook(
        Dispatcher $jobDispatcher,
        Subscription $subscription,
        AdfPayloadService $adfPayloadService,
        MigrationStep $migrationStep,
        string $messageType,
        array $exceptionPayload,
        string $reference
    ): void {
        if ($this->adfWebhookHasBeenCalled || $this->migrationSource !== MigrationSource::AZURE_DATA_FACTORY) {
            return;
        }

        $jobDispatcher->dispatch(
            new AzureDataFactoryJobRequester(
                message: AzureDataFactoryMessage::create(
                    $messageType,
                    [
                        ...$adfPayloadService->fetchMigrationADFPayload($subscription, $migrationStep)->toArray(),
                        ...$exceptionPayload,
                    ]
                ),
                reference: $reference
            )
        );

        $this->adfWebhookHasBeenCalled = true;
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
        return Container::getInstance()->make($class);
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::FERRY;
    }

    protected function getJobId(): string
    {
        return $this->job?->getJobId() ?? 'unknown';
    }

    private function getMigratedCustomer(Subscription $subscription): MigratedCustomer
    {
        return $subscription
            ->customer()
            ->firstOrFail()
            ->migratedCustomers()
            ->firstOrFail();
    }
}
