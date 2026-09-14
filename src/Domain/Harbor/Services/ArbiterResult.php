<?php

declare(strict_types=1);

namespace Waterfront\Domain\Harbor\Services;

class ArbiterResult
{
    public function __construct(
        private readonly bool $propagationAllowed,
        private readonly ?string $reason,
    ) {
    }

    public function isPropagationAllowed(): bool
    {
        return $this->propagationAllowed;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }
}
