<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Backup\Results;

use Exception;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;
use Waterfront\Domain\Provision\Results\AbstractProvisionResult;
use Waterfront\Domain\Provision\Validation\ValidationResult;
use Waterfront\Infra\AcronisClient\DTO\Tenants\TenantUsages;

class BackupUsagesResult extends AbstractProvisionResult
{
    public bool $failed {
        get => parent::$failed::get() || $this->tenantUsages === null;
    }

    public function __construct(
        ProvisionRequestInterface $provisionData,
        ProvisionStatus $provisionStatus,
        public ?TenantUsages $tenantUsages = null,
        ?Exception $exception = null,
        ?ValidationResult $validationResult = null,
    ) {
        parent::__construct($provisionData, $provisionStatus, $exception, $validationResult);
    }
}
