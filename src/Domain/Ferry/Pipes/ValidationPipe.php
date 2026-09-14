<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Pipes;

use Closure;
use Illuminate\Container\Container;
use Psr\Log\LoggerInterface;
use Waterfront\Domain\Domains\Models\DomainProviderBusinessUnit;
use Waterfront\Domain\Ferry\Dto\Validation\ValidationErrorResult;
use Waterfront\Domain\Ferry\Dto\Validation\ValidationPayload;
use Waterfront\Domain\Ferry\Dto\Validation\ValidationResult;
use Waterfront\Domain\Ferry\Enums\MigrationValidation;
use Waterfront\Domain\Ferry\Exceptions\DomainBusinessUnitNotFoundException;
use Waterfront\Domain\Ferry\Exceptions\NoCredentialsForDomainBusinessUnitException;
use Waterfront\Domain\Ferry\Services\DomainAndSslMigrationService;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Support\Enums\LoggingContextKeys;

abstract class ValidationPipe implements ValidationPipeInterface
{
    /**
     * @param array<string, mixed> $data
     */
    protected function addValidationResult(
        ValidationPayload $validationPayload,
        MigrationValidation $migrationValidationKey,
        string $message,
        ?string $referenceSubscriptionId = null,
        array $data = [],
    ): void {
        $validationPayload->addValidationResult(
            $this->getValidationIdentifier(),
            new ValidationResult(
                id: $migrationValidationKey,
                message: $message,
                referenceSubscriptionId: $referenceSubscriptionId,
                data: $data,
            ),
        );
    }

    /**
     * @param array<string, array<int, string>> $messages
     */
    protected function addValidationErrorResult(
        ValidationPayload $validationPayload,
        MigrationValidation $migrationValidationKey,
        array $messages,
    ): void {
        $validationPayload->addValidationResult(
            $this->getValidationIdentifier(),
            new ValidationErrorResult(
                id: $migrationValidationKey,
                messages: $messages,
            ),
        );
    }

    protected function finishPipe(
        MigrationValidation $completedPipeId,
        ValidationPayload $payload,
        LoggerInterface $logger,
        Closure $next,
    ): ValidationPayload {
        /**
         * We'd rather log a split json string than have an error because the context is too big.
         *
         * @see https://yh-jira.atlassian.net/browse/SWD-10015
         */
        $validationResults = $payload->validationResults;
        $validationResultsAsJson = json_encode($validationResults, JSON_THROW_ON_ERROR);
        $contextLengthLimit = 200000; // the exact value of this isn't 100% clear, so we might change this later

        if (strlen($validationResultsAsJson) < $contextLengthLimit) {
            $logger->debug(
                'Finished validation of data pipe',
                [
                    LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                    LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
                    LoggingContextKeys::META => [
                        'pipe' => $this->getValidationIdentifier()->value,
                        'validation_results' => $validationResults,
                    ],
                ],
            );
        } else {
            $messageParts = str_split($validationResultsAsJson, $contextLengthLimit);
            $total = count($messageParts);

            foreach ($messageParts as $index => $messagePart) {
                $logger->debug(
                    sprintf(
                        'Finished validation of data pipe (%d / %d)',
                        $index + 1,
                        $total,
                    ),
                    [
                        LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                        LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
                        LoggingContextKeys::META => [
                            'pipe' => $this->getValidationIdentifier()->value,
                            'validation_results' => $messagePart,
                        ],
                    ],
                );
            }
        }

        $this->addCompleteResult($completedPipeId, $payload);

        return $next($payload);
    }

    protected function getBusinessUnitOrFailValidation(
        string $businessUnitSlug,
        ProviderSlug $driver,
        ValidationPayload $payload,
    ): ?DomainProviderBusinessUnit {
        $container = Container::getInstance();
        $domainAndSslMigrationService = $container->make(DomainAndSslMigrationService::class);
        $logger = $container->make(LoggerInterface::class);

        try {
            return $domainAndSslMigrationService->getProviderBusinessUnit($businessUnitSlug, $driver);
        } catch (DomainBusinessUnitNotFoundException $exception) {
            $message = sprintf(
                "Couldn't find domain provider business unit by slug [%s] from backend %s, exception: %s",
                $businessUnitSlug,
                $driver->value,
                $exception->getMessage(),
            );

            $this->addValidationResult(
                $payload,
                MigrationValidation::DOMAIN_MIGRATION_BUSINESS_UNIT_FAILED,
                $message,
            );

            $logger->debug($message, [
                LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
            ]);

            return null;
        } catch (NoCredentialsForDomainBusinessUnitException $exception) {
            $message = sprintf(
                'No credentials set for [%s] with business unit [%s], exception: %s',
                $driver->value,
                $businessUnitSlug,
                $exception->getMessage(),
            );

            $this->addValidationResult(
                $payload,
                MigrationValidation::DOMAIN_MIGRATION_DRIVER_CREDENTIALS_FAILED,
                $message,
            );

            $logger->debug($message, [
                LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
            ]);

            return null;
        }
    }

    private function addCompleteResult(MigrationValidation $completedPipeId, ValidationPayload $payload): void
    {
        $completedMessage = sprintf(
            $this->getValidationIdentifier()->value . ' reference: %s',
            $payload->validationReference,
        );

        $payload->addValidationResult(
            $this->getValidationIdentifier(),
            new ValidationResult(
                id: $completedPipeId,
                message: $completedMessage,
            ),
        );
    }
}
