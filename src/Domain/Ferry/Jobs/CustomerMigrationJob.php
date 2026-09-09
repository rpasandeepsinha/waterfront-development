<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Jobs;

use Illuminate\Bus\Dispatcher;
use Illuminate\Support\Arr;
use Illuminate\Validation\Factory as ValidatorFactory;
use Illuminate\Validation\ValidationException;
use Psr\Log\LoggerInterface;
use Throwable;
use Waterfront\Apps\API\Ferry\Request\Convertors\MigrationCustomerPayloadToDtoConverter;
use Waterfront\Apps\API\Ferry\Request\MigrationValidationLibrary;
use Waterfront\Apps\API\Ferry\Request\Rules\CustomerMigrationRules;
use Waterfront\Domain\Ferry\Actions\Customers\StoreMigratedCustomerAction;
use Waterfront\Domain\Ferry\Enums\AzureDataFactoryMessageType;
use Waterfront\Domain\Ferry\Enums\MigrationStep;
use Waterfront\Domain\Ferry\Jobs\AzureDataFactory\AzureDataFactoryJobRequester;
use Waterfront\Domain\Ferry\Jobs\AzureDataFactory\AzureDataFactoryMessage;
use Waterfront\Domain\Ferry\Services\AdfPayloadService;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;
use Webmozart\Assert\Assert;

class CustomerMigrationJob extends AbstractQueueableJob
{
    /**
     * @param array<mixed> $customerRawData
     */
    public function __construct(private readonly array $customerRawData)
    {
        parent::__construct();
    }

    public function handle(
        StoreMigratedCustomerAction $storeMigratedCustomerAction,
        Dispatcher $dispatcher,
        AdfPayloadService $adfPayloadService,
        MigrationCustomerPayloadToDtoConverter $converter,
        LoggerInterface $logger,
        ValidatorFactory $validatorFactory,
        CustomerMigrationRules $customerMigrationRules,
    ): void {
        $rules = $customerMigrationRules->getRules($this->customerRawData);

        $validator = $validatorFactory->make(
            $this->customerRawData,
            $rules,
            $this->getCustomMessages()
        );

        // Already went through validation.
        $referenceCustomerId = Arr::get($this->customerRawData, 'referenceCustomerId');
        Assert::string($referenceCustomerId);

        try {
            $logger->info('Starting validation for customer in job.', [
                LoggingContextKeys::QUEUE_JOB_ID => $this->job?->getJobId(),
                LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID => $referenceCustomerId,
            ]);

            $validator->validate();
        } catch (ValidationException $exception) {
            $logger->info('Encountered validation exception for customer in job.', [
                LoggingContextKeys::QUEUE_JOB_ID => $this->job?->getJobId(),
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
                        ]
                    ),
                    reference: $referenceCustomerId,
                )
            );
            return;
        }

        try {
            $customerDto = $converter->convert($this->customerRawData);
            $migratedDataList = $storeMigratedCustomerAction->execute($customerDto);

            /** @var int $waterfrontCustomerId */
            $waterfrontCustomerId = $migratedDataList['customerId'];

            /** @phpstan-ignore-next-line  */
        } catch (Throwable $exception) {
            $logger->info('Encountered exception while creating migrated customer in job.', [
                LoggingContextKeys::QUEUE_JOB_ID => $this->job?->getJobId(),
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
                )
            );
            return;
        }

        $dispatcher->dispatch(
            new AzureDataFactoryJobRequester(
                message: AzureDataFactoryMessage::create(
                    AzureDataFactoryMessageType::MIGRATION_EXECUTED->value,
                    [
                        ...$adfPayloadService->fetchMigrationBulkCustomerPayload(
                            customerDTO: $customerDto,
                            waterfrontCustomerId: $waterfrontCustomerId,
                            migrationStep: MigrationStep::CUSTOMER
                        )->toArray(),
                    ]
                ),
                reference: $customerDto->buCustomerNumber
            )
        );
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::FERRY_BULK;
    }

    /**
     * @return string[]
     */
    private function getCustomMessages(): array
    {
        $bulkMessages = [];
        $singleMessages =  MigrationValidationLibrary::customerMessages();

        foreach ($singleMessages as $key => $singleMessage) {
            $bulkMessages['*.' . $key] = $singleMessage;
        }

        return $bulkMessages;
    }
}
