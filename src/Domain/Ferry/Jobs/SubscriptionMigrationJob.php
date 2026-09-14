<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Jobs;

use Illuminate\Bus\Dispatcher;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Illuminate\Validation\Factory as ValidatorFactory;
use Illuminate\Validation\ValidationException;
use Psr\Log\LoggerInterface;
use Throwable;
use Waterfront\Apps\API\Ferry\Request\Convertors\MigrationSubscriptionPayloadToDtoConverter;
use Waterfront\Apps\API\Ferry\Request\Rules\SubscriptionMigrationRules;
use Waterfront\Domain\Customers\DTO\CreateSubscriptionsDTO;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Ferry\Actions\Subscriptions\StoreSubscriptionAction;
use Waterfront\Domain\Ferry\Dto\ResponseDto;
use Waterfront\Domain\Ferry\Enums\AzureDataFactoryMessageType;
use Waterfront\Domain\Ferry\Enums\MigrationStep;
use Waterfront\Domain\Ferry\Jobs\AzureDataFactory\AzureDataFactoryJobRequester;
use Waterfront\Domain\Ferry\Jobs\AzureDataFactory\AzureDataFactoryMessage;
use Waterfront\Domain\Ferry\Services\AdfPayloadService;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;
use Webmozart\Assert\Assert;

class SubscriptionMigrationJob extends AbstractQueueableJob
{
    /**
     * @param array<mixed> $subscriptionRawData
     */
    public function __construct(
        private readonly array $subscriptionRawData,
    ) {
        parent::__construct();
    }

    public function handle(
        StoreSubscriptionAction $storeSubscriptionAction,
        Dispatcher $dispatcher,
        AdfPayloadService $adfPayloadService,
        MigrationSubscriptionPayloadToDtoConverter $converter,
        LoggerInterface $logger,
        SubscriptionMigrationRules $subscriptionMigrationRules,
        ValidatorFactory $validatorFactory,
    ): void {
        $customerId = Arr::get($this->subscriptionRawData, 'customer_id');
        Assert::integer($customerId);

        $referenceCustomerId = Arr::get($this->subscriptionRawData, 'reference_customer_id');
        Assert::string($referenceCustomerId);

        $passes = $this->validate(
            customerId: $customerId,
            referenceCustomerId: $referenceCustomerId,
            subscriptionMigrationRules: $subscriptionMigrationRules,
            validatorFactory: $validatorFactory,
            logger: $logger,
            dispatcher: $dispatcher,
        );

        if (! $passes) {
            return;
        }

        $createSubscriptionsDTO = $this->storeSubscriptions(
            customerId: $customerId,
            referenceCustomerId: $referenceCustomerId,
            storeSubscriptionAction: $storeSubscriptionAction,
            converter: $converter,
            logger: $logger,
            dispatcher: $dispatcher,
        );

        if ($createSubscriptionsDTO !== null) {
            $dispatcher->dispatch(
                new AzureDataFactoryJobRequester(
                    message: AzureDataFactoryMessage::create(
                        AzureDataFactoryMessageType::MIGRATION_EXECUTED->value,
                        [
                            ...$adfPayloadService
                                ->fetchMigrationBulkSubscriptionPayload(
                                    createSubscriptionsDTO: $createSubscriptionsDTO,
                                    subscriptions: $this->getSubscriptionsMap($createSubscriptionsDTO),
                                    migrationStep: MigrationStep::SUBSCRIPTION,
                                )
                                ->toArray(),
                        ],
                    ),
                    reference: $createSubscriptionsDTO->getReferenceCustomerId(),
                ),
            );
        }
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::FERRY_BULK;
    }

    /**
     * @return array<int<0, max>, array{waterfront_subscription_id: int, reference_subscription_id: string|null}>
     */
    private function getSubscriptionsMap(CreateSubscriptionsDTO $createSubscriptionsDTO): array
    {
        $subscriptions = $createSubscriptionsDTO->getCustomer()->subscriptions;

        $list = [];
        foreach ($subscriptions as $subscription) {
            $list[] = [
                'waterfront_subscription_id' => $subscription->id,
                'reference_subscription_id' => $subscription->migratedSubscriptions->isNotEmpty()
                    ? $subscription->migratedSubscriptions->firstOrFail()->reference_subscription_id
                    : null,
            ];
        }

        return $list;
    }

    private function validate(
        int $customerId,
        string $referenceCustomerId,
        SubscriptionMigrationRules $subscriptionMigrationRules,
        ValidatorFactory $validatorFactory,
        LoggerInterface $logger,
        Dispatcher $dispatcher,
    ): bool {
        try {
            $customer = Customer::findOrFail($customerId);

            $rules = $subscriptionMigrationRules->getRules($customer);

            $validator = $validatorFactory->make(
                $this->subscriptionRawData,
                $rules,
            );

            $logger->info('Starting validation for subscriptions for customer in job.', [
                LoggingContextKeys::QUEUE_JOB_ID => $this->job?->getJobId(),
                LoggingContextKeys::CUSTOMER_ID => $customerId,
                LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID => $referenceCustomerId,
            ]);

            $validator->validate();

            return true;
        } catch (ValidationException $exception) {
            $logger->info('Encountered validation exception while inserting subscriptions for customer in job.', [
                LoggingContextKeys::QUEUE_JOB_ID => $this->job?->getJobId(),
                LoggingContextKeys::CUSTOMER_ID => $customerId,
                LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID => $referenceCustomerId,
                LoggingContextKeys::META => [
                    'validation_errors' => $exception->errors(),
                ],
            ]);

            $dispatcher->dispatch(
                new AzureDataFactoryJobRequester(
                    message: AzureDataFactoryMessage::create(
                        AzureDataFactoryMessageType::MIGRATION_EXECUTED_UNSUCCESSFUL->value,
                        [
                            'validation_errors' => $exception->errors(),
                        ],
                    ),
                    reference: $referenceCustomerId,
                ),
            );
        } catch (ModelNotFoundException $exception) {
            $logger->info('Encountered validation exception while inserting subscriptions for customer in job.', [
                LoggingContextKeys::QUEUE_JOB_ID => $this->job?->getJobId(),
                LoggingContextKeys::CUSTOMER_ID => $customerId,
                LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID => $referenceCustomerId,
                LoggingContextKeys::EXCEPTION => $exception,
            ]);

            $exceptionPayload = ['error' => $exception->__toString()];

            $dispatcher->dispatch(
                new AzureDataFactoryJobRequester(
                    message: AzureDataFactoryMessage::create(
                        AzureDataFactoryMessageType::MIGRATION_EXECUTED_UNSUCCESSFUL->value,
                        $exceptionPayload,
                    ),
                    reference: $referenceCustomerId,
                ),
            );
        }

        return false;
    }

    private function storeSubscriptions(
        int $customerId,
        string $referenceCustomerId,
        StoreSubscriptionAction $storeSubscriptionAction,
        MigrationSubscriptionPayloadToDtoConverter $converter,
        LoggerInterface $logger,
        Dispatcher $dispatcher,
    ): ?CreateSubscriptionsDTO {
        try {
            $createSubscriptionsDTO = $converter->convert($this->subscriptionRawData);

            $logger->info('Starting to insert subscriptions for customer in job.', [
                LoggingContextKeys::QUEUE_JOB_ID => $this->job?->getJobId(),
                LoggingContextKeys::CUSTOMER_ID => $createSubscriptionsDTO->getCustomer()->id,
                LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID =>
                    $createSubscriptionsDTO->getReferenceCustomerId(),
            ]);

            $storeSubscriptionAction->execute(
                createSubscriptions: $createSubscriptionsDTO,
                responseDto: new ResponseDto(),
            );

            $logger->info('Completed inserting subscriptions for customer in job.', [
                LoggingContextKeys::QUEUE_JOB_ID => $this->job?->getJobId(),
                LoggingContextKeys::CUSTOMER_ID => $createSubscriptionsDTO->getCustomer()->id,
                LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID =>
                    $createSubscriptionsDTO->getReferenceCustomerId(),
            ]);

            return $createSubscriptionsDTO;

            /** @phpstan-ignore-next-line  */
        } catch (Throwable $exception) {
            $logger->info('Encountered exception while inserting subscriptions for customer in job.', [
                LoggingContextKeys::QUEUE_JOB_ID => $this->job?->getJobId(),
                LoggingContextKeys::CUSTOMER_ID => $customerId,
                LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID => $referenceCustomerId,
                LoggingContextKeys::EXCEPTION => $exception,
            ]);

            $exceptionPayload = ['error' => $exception->__toString()];

            $dispatcher->dispatch(
                new AzureDataFactoryJobRequester(
                    message: AzureDataFactoryMessage::create(
                        AzureDataFactoryMessageType::MIGRATION_EXECUTED_UNSUCCESSFUL->value,
                        $exceptionPayload,
                    ),
                    reference: $referenceCustomerId,
                ),
            );

            return null;
        }
    }
}
