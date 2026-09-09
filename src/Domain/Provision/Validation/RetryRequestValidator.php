<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Validation;

use Waterfront\Domain\Provision\Enums\ProvisionErrorMessage;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;
use Waterfront\Domain\Provision\Interfaces\ProvisionResultInterface;
use Waterfront\Domain\Provision\Repositories\ProvisioningRequestRepository;
use Waterfront\Domain\Provision\Results\ProvisionResult;

class RetryRequestValidator
{
    public function __construct(
        public readonly ProvisioningRequestRepository $provisioningRequestRepository,
    ) {
    }

    public function validate(ProvisionRequestInterface $provisionData): ?ProvisionResultInterface
    {
        if (! $provisionData->isRetry()) {
            return null;
        }

        $validationResult = new ValidationResult();
        $originRequest = $this->provisioningRequestRepository->findByUuid($provisionData->retryOf);

        if ($originRequest === null) {
            $validationResult->addValidationError(
                valueName: 'retryOf',
                message: ProvisionErrorMessage::RETRY_ORIGIN_NOT_FOUND->value
            );

            return new ProvisionResult(
                provisionData: $provisionData,
                provisionStatus: ProvisionStatus::VALIDATION_ERROR,
                validationResult: $validationResult,
            );
        }

        if ($originRequest->request_type !== $provisionData->type) {
            $validationResult->addValidationError(
                valueName: 'retryOf',
                message: ProvisionErrorMessage::RETRY_ORIGIN_TYPE_MISMATCH->value
            );
        }

        if ($originRequest->request_name !== $provisionData->name) {
            $validationResult->addValidationError(
                valueName: 'retryOf',
                message: ProvisionErrorMessage::RETRY_ORIGIN_NAME_MISMATCH->value
            );
        }

        if ($originRequest->retry_of_request_id !== null) {
            $validationResult->addValidationError(
                valueName: 'retryOf',
                message: ProvisionErrorMessage::RETRY_ORIGIN_IS_RETRY->value
            );
        }

        if ($originRequest->result === null || $originRequest->result->succeeded) {
            $validationResult->addValidationError(
                valueName: 'retryOf',
                message: ProvisionErrorMessage::RETRY_ORIGIN_NOT_FAILED->value
            );
        }

        return $validationResult->isValid
            ? null
            : new ProvisionResult(
                provisionData: $provisionData,
                provisionStatus: ProvisionStatus::VALIDATION_ERROR,
                validationResult: $validationResult,
            );
    }
}
