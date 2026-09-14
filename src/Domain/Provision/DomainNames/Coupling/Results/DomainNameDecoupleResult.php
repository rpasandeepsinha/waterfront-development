<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\DomainNames\Coupling\Results;

use Throwable;
use Waterfront\Domain\Provision\DomainNames\Coupling\Models\DomainNameCoupleDeployment;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;
use Waterfront\Domain\Provision\Results\AbstractProvisionResult;
use Waterfront\Domain\Provision\Validation\ValidationResult;

class DomainNameDecoupleResult extends AbstractProvisionResult
{
    public function __construct(
        public ProvisionRequestInterface $provisionData,
        public ProvisionStatus $provisionStatus,
        public DomainNameCoupleDeployment $domainNameCoupleDeployment,
        public ?Throwable $exception = null,
        public ?ValidationResult $validationResult = null,
    ) {
        parent::__construct(
            provisionData: $provisionData,
            provisionStatus: $provisionStatus,
            exception: $exception,
            validationResult: $validationResult,
        );
    }
}
