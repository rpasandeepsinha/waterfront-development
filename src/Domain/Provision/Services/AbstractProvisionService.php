<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Services;

use Illuminate\Contracts\Validation\Validator;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;
use Waterfront\Domain\Provision\Interfaces\ProvisionServiceInterface;
use Waterfront\Domain\Provision\Results\AbstractProvisionResult;
use Waterfront\Domain\Provision\Results\ProvisionResult;
use Waterfront\Domain\Provision\Validation\ValidationResult;

abstract class AbstractProvisionService implements ProvisionServiceInterface
{
    protected function createFailedValidationResult(
        ProvisionRequestInterface $provisionData,
        Validator $validator,
    ): AbstractProvisionResult {
        $validationResult = ValidationResult::fromValidator($validator);

        return new ProvisionResult(
            provisionData: $provisionData,
            provisionStatus: ProvisionStatus::VALIDATION_ERROR,
            validationResult: $validationResult,
        );
    }

    protected function getProviderForRequest(ProvisionRequestInterface $provisionData): ProvisionProvider
    {
        return $provisionData->provider ?? $this->getDefaultProvider();
    }
}
