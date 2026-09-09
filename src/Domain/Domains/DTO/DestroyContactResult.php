<?php

declare(strict_types=1);

namespace Waterfront\Domain\Domains\DTO;

class DestroyContactResult
{
    public function __construct(private readonly bool $success)
    {
    }

    public function isSuccessful(): bool
    {
        return $this->success;
    }
}
