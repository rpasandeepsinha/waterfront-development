<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Interfaces;

use Throwable;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Validation\ValidationResult;

interface ProvisionResultInterface
{
    public ProvisionRequestInterface $provisionData { get; set; }

    public ProvisionStatus $provisionStatus { get; set; }

    public ?Throwable $exception { get; set; }

    public ?ValidationResult $validationResult { get; set; }

    public bool $failed { get; }

    public bool $succeeded { get; }
}
