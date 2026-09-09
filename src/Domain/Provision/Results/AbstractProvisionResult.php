<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Results;

use Throwable;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;
use Waterfront\Domain\Provision\Interfaces\ProvisionResultInterface;
use Waterfront\Domain\Provision\Validation\ValidationResult;

abstract class AbstractProvisionResult implements ProvisionResultInterface
{
    public bool $failed {
        get => in_array($this->provisionStatus, ProvisionStatus::getFailedStatuses(), true);
    }

    public bool $succeeded {
        get => ! $this->failed;
    }

    public function __construct(
        public ProvisionRequestInterface $provisionData,
        public ProvisionStatus $provisionStatus,
        public ?Throwable $exception = null,
        public ?ValidationResult $validationResult = null
    ) {
    }
}
