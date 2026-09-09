<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Pipes;

use Closure;
use Illuminate\Validation\Factory as ValidatorFactory;
use Illuminate\Validation\ValidationException;
use Psr\Log\LoggerInterface;
use Throwable;
use Waterfront\Apps\API\Ferry\Request\MigrationValidationLibrary;
use Waterfront\Apps\API\Ferry\Request\Rules\CustomerMigrationRules;
use Waterfront\Domain\Ferry\Dto\Validation\ValidationPayload;
use Waterfront\Domain\Ferry\Enums\MigrationValidation;
use Waterfront\Domain\Ferry\Enums\MigrationValidationPipes;
use Waterfront\Support\Enums\LoggingContextKeys;

class CustomerPipe extends ValidationPipe
{
    public function __construct(
        private readonly CustomerMigrationRules $customerMigrationRules,
        private readonly LoggerInterface $logger,
        private readonly ValidatorFactory $validationFactory
    ) {
    }

    public function handle(ValidationPayload $payload, Closure $next): ValidationPayload
    {
        $this->logger->debug(
            'Starting validation of customer migration',
            [
                LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
            ]
        );

        $payload->addValidationTimeline(
            pipeline: $this->getValidationIdentifier(),
            message: 'Start',
            id: $payload->validationReference
        );

        $customerData = $payload->customer;
        $rules = $this->customerMigrationRules->getRules($customerData);
        $messages = $this->getBaseMessages();

        $validator = $this->validationFactory->make(
            $customerData,
            $rules,
            $messages
        );

        $this->logger->debug(
            'Validating customer data',
            [
                LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
                LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID => $customerData['referenceCustomerId'],
            ]
        );

        try {
            $validator->validate();
        } catch (ValidationException $exception) {
            $this->addValidationErrorResult(
                $payload,
                MigrationValidation::DEFAULT_VALIDATION,
                $exception->validator->errors()->toArray()
            );

            $payload->addValidationTimeline(
                pipeline: $this->getValidationIdentifier(),
                message: 'Validation finish',
                id: $payload->validationReference
            );

            return $this->finishPipe(MigrationValidation::CUSTOMER_PIPE_PASSED, $payload, $this->logger, $next);
        } catch (Throwable $exception) { // @phpstan-ignore-line
            $this->logger->error(
                'Unknown exception while validating customer data',
                [
                    LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                    LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
                    LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID => $customerData['referenceCustomerId'],
                    LoggingContextKeys::EXCEPTION => $exception,
                ]
            );
            $this->addValidationResult(
                $payload,
                MigrationValidation::CUSTOMER_PIPE_VALIDATION_UNKNOWN_EXCEPTION,
                $exception->getMessage(),
            );

            $payload->addValidationTimeline(
                pipeline: $this->getValidationIdentifier(),
                message: 'Exception finish',
                id: $payload->validationReference
            );

            return $this->finishPipe(MigrationValidation::CUSTOMER_PIPE_PASSED, $payload, $this->logger, $next);
        }

        $this->logger->debug(
            'Done with validation rules, now checking if migration customer already exists',
            [
                LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
                LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID => $customerData['referenceCustomerId'],
            ]
        );

        $this->logger->debug(
            'Reference coupling with legacy customer ID for migration',
            [
                LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
                LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID => $customerData['referenceCustomerId'],
            ]
        );

        $payload->addValidationTimeline(
            pipeline: $this->getValidationIdentifier(),
            message: 'Finish',
            id: $payload->validationReference
        );

        return $this->finishPipe(MigrationValidation::CUSTOMER_PIPE_PASSED, $payload, $this->logger, $next);
    }

    public function getValidationIdentifier(): MigrationValidationPipes
    {
        return MigrationValidationPipes::CUSTOMER;
    }

    /**
     * @return array<string, mixed>
     */
    private function getBaseMessages(): array
    {
        return MigrationValidationLibrary::customerMessages();
    }
}
