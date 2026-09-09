<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Microsoft365\Results;

use Throwable;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;
use Waterfront\Domain\Provision\Validation\ValidationResult;

class PromoteDomainResult extends Microsoft365Result
{
    public bool $failed {
        get => parent::$failed::get() || ! $this->isPromoted;
    }

    public function __construct(
        public ProvisionRequestInterface $provisionData,
        public ProvisionStatus $provisionStatus,
        public bool $isPromoted,
        public ?Throwable $exception = null,
        public ?ValidationResult $validationResult = null
    ) {
        parent::__construct(
            provisionData: $provisionData,
            provisionStatus: $provisionStatus,
            exception: $exception,
            validationResult: $validationResult
        );
    }
}
