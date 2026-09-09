<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\DTO;

readonly class DowngradeCheckResult
{
    public function __construct(public bool $isSuccessful, public ?string $message)
    {
    }
}
