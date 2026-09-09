<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Backup\Results;

use SensitiveParameter;
use Throwable;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;
use Waterfront\Domain\Provision\Results\AbstractProvisionResult;
use Waterfront\Domain\Provision\Validation\ValidationResult;

class BackupCreateResult extends AbstractProvisionResult
{
    public function __construct(
        ProvisionRequestInterface $provisionData,
        ProvisionStatus $provisionStatus,
        public ?string $username = null,
        #[SensitiveParameter]
        public ?string $password = null,
        ?Throwable $exception = null,
        ?ValidationResult $validationResult = null
    ) {
        parent::__construct($provisionData, $provisionStatus, $exception, $validationResult);
    }
}
