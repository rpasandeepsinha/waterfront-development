<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Jobs;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Psr\Log\LoggerInterface;
use Throwable;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Models\MigratedCustomer;
use Waterfront\Domain\Ferry\Enums\AzureDataFactoryMessageType;
use Waterfront\Domain\Ferry\Jobs\AzureDataFactory\AzureDataFactoryJobRequester;
use Waterfront\Domain\Ferry\Jobs\AzureDataFactory\AzureDataFactoryMessage;
use Waterfront\Domain\Payments\Actions\CreateDirectDebitMandateAction;
use Waterfront\Domain\Payments\Models\Mandate;
use Waterfront\Infra\MollieClient\DTO\Mandates\MollieMandateDirectDebitCreateDTO;
use Waterfront\Infra\MollieClient\Exceptions\MollieMandateApiException;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;

class CreateDirectDebitMandateJob extends AbstractQueueableJob implements ShouldQueue
{
    public int $tries = 5;

    public int $timeout = 60;

    public int $backoff = 60;

    public function __construct(
        public readonly Customer $customer,
        public readonly MollieMandateDirectDebitCreateDTO $mollieMandateDirectDebitCreateDTO,
        public readonly ?MigratedCustomer $migratedCustomer = null,
    ) {
        parent::__construct();
    }

    public function handle(
        CreateDirectDebitMandateAction $createDirectDebitMandateAction,
        LoggerInterface $logger,
        Dispatcher $dispatcher,
    ): void {
        $logger->info(
            'Start creating direct debit mandate',
            [
                LoggingContextKeys::QUEUE_JOB_ID => $this->getJobUuid(),
                LoggingContextKeys::QUEUE_ATTEMPT => $this->attempts(),
                LoggingContextKeys::CUSTOMER_ID => $this->customer->id,
                LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID =>
                    $this->migratedCustomer?->reference_customer_number,
            ],
        );

        try {
            try {
                $mandate = $createDirectDebitMandateAction->execute(
                    customer: $this->customer,
                    consumerName: $this->mollieMandateDirectDebitCreateDTO->consumerName,
                    consumerAccount: $this->mollieMandateDirectDebitCreateDTO->consumerAccount,
                    signatureDate: new CarbonImmutable($this->mollieMandateDirectDebitCreateDTO->signatureDate),
                    consumerBic: $this->mollieMandateDirectDebitCreateDTO->consumerBic,
                );
            } catch (MollieMandateApiException $exception) {
                $mandate = $this->handleBICUnprocessable(
                    exception: $exception,
                    createDirectDebitMandateAction: $createDirectDebitMandateAction,
                    logger: $logger,
                );
            }
        } catch (Throwable $exception) {
            $this->handleDefaultExceptionState(
                exception: $exception,
                logger: $logger,
                dispatcher: $dispatcher,
            );

            throw $exception;
        }

        $logger->info(
            'Created direct debit mandate for customer',
            [
                LoggingContextKeys::QUEUE_JOB_ID => $this->getJobUuid(),
                LoggingContextKeys::QUEUE_ATTEMPT => $this->attempts(),
                LoggingContextKeys::CUSTOMER_ID => $this->customer->id,
                LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID =>
                    $this->migratedCustomer?->reference_customer_number,
                LoggingContextKeys::META => [
                    'payt_mandate_reference_id' => $mandate->payt_mandate_reference_id,
                    'signature_date' => $mandate->signature_date->format('Y-m-d'),
                ],
            ],
        );

        if ($this->migratedCustomer instanceof MigratedCustomer) {
            $mandate->migratedCustomers()->sync($this->migratedCustomer);

            $logger->info(
                'Attached direct debit mandate to migrated customer',
                [
                    LoggingContextKeys::QUEUE_JOB_ID => $this->getJobUuid(),
                    LoggingContextKeys::QUEUE_ATTEMPT => $this->attempts(),
                    LoggingContextKeys::CUSTOMER_ID => $this->customer->id,
                    LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID =>
                        $this->migratedCustomer->reference_customer_number,
                    LoggingContextKeys::META => [
                        'payt_mandate_reference_id' => $mandate->payt_mandate_reference_id,
                    ],
                ],
            );
        }
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::CUSTOMERS;
    }

    protected function getJobUuid(): string
    {
        return $this->job?->uuid() ?? 'unknown';
    }

    private function handleBICUnprocessable(
        MollieMandateApiException $exception,
        CreateDirectDebitMandateAction $createDirectDebitMandateAction,
        LoggerInterface $logger,
    ): Mandate {
        if ($exception->status === 422 && str_contains($exception->detail, 'BIC')) {
            $logger->warning(
                'Retry direct debit mandate creation, but without the BIC',
                [
                    LoggingContextKeys::QUEUE_JOB_ID => $this->getJobUuid(),
                    LoggingContextKeys::QUEUE_ATTEMPT => $this->attempts(),
                    LoggingContextKeys::CUSTOMER_ID => $this->customer->id,
                    LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID =>
                        $this->migratedCustomer?->reference_customer_number,
                    LoggingContextKeys::EXCEPTION => $exception,
                ],
            );

            return $createDirectDebitMandateAction->execute(
                customer: $this->customer,
                consumerName: $this->mollieMandateDirectDebitCreateDTO->consumerName,
                consumerAccount: $this->mollieMandateDirectDebitCreateDTO->consumerAccount,
                signatureDate: new CarbonImmutable($this->mollieMandateDirectDebitCreateDTO->signatureDate),
            );
        }

        throw $exception;
    }

    private function handleDefaultExceptionState(
        Throwable $exception,
        LoggerInterface $logger,
        Dispatcher $dispatcher,
    ): void {
        $logger->error(
            'Unknown exception while creating direct debit mandate',
            [
                LoggingContextKeys::QUEUE_JOB_ID => $this->getJobUuid(),
                LoggingContextKeys::QUEUE_ATTEMPT => $this->attempts(),
                LoggingContextKeys::CUSTOMER_ID => $this->customer->id,
                LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID =>
                    $this->migratedCustomer?->reference_customer_number,
                LoggingContextKeys::EXCEPTION => $exception,
            ],
        );

        if ($this->attempts() >= $this->tries && $this->migratedCustomer instanceof MigratedCustomer) {
            $this->callADFWebhook(
                $dispatcher,
                $this->migratedCustomer,
                AzureDataFactoryMessageType::DIRECT_DEBIT_CREATION_UNSUCCESSFUL,
                $exception->__toString(),
                $this->migratedCustomer->reference_customer_number,
            );
        }
    }

    private function callADFWebhook(
        Dispatcher $jobDispatcher,
        MigratedCustomer $migratedCustomer,
        AzureDataFactoryMessageType $messageType,
        string $exceptionString,
        string $reference,
    ): void {
        $jobDispatcher->dispatch(
            new AzureDataFactoryJobRequester(
                message: AzureDataFactoryMessage::create(
                    $messageType->value,
                    [
                        'reference_customer_number' => $migratedCustomer->reference_customer_number,
                        'reference_name' => $migratedCustomer->reference_name,
                        'error' => $exceptionString,
                    ],
                ),
                reference: $reference,
            ),
        );
    }
}
