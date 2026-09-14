<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Backup\Results;

use SensitiveParameter;
use Throwable;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;
use Waterfront\Domain\Provision\Results\AbstractProvisionResult;
use Waterfront\Domain\Provision\Validation\ValidationResult;

class BackupSsoResult extends AbstractProvisionResult
{
    public bool $failed {
        get => parent::$failed::get() || $this->ssoUrl === null;
    }

    public function __construct(
        ProvisionRequestInterface $provisionData,
        ProvisionStatus $provisionStatus,
        #[SensitiveParameter]
        public ?string $ssoUrl = null,
        ?Throwable $exception = null,
        ?ValidationResult $validationResult = null,
    ) {
        parent::__construct($provisionData, $provisionStatus, $exception, $validationResult);
    }
}
