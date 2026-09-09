<?php

declare(strict_types=1);

namespace Tests\Domain\Provision\Stubs;

use Throwable;
use Waterfront\Domain\Provision\Backup\Requests\CreateBackupRequest;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Results\AbstractProvisionResult;
use Waterfront\Domain\Provision\Validation\ValidationResult;

class ProvisionNestedChildResult extends AbstractProvisionResult
{
    public function __construct(
        CreateBackupRequest $provisionData,
        ProvisionStatus $provisionStatus,
        public ?string $token = null,
        ?Throwable $exception = null,
        ?ValidationResult $validationResult = null,
    ) {
        parent::__construct($provisionData, $provisionStatus, $exception, $validationResult);
    }
}
