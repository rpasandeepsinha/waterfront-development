<?php

declare(strict_types=1);

namespace Waterfront\Domain\VPS\DTO;

readonly class DeleteSshKeyPairResponse
{
    public function __construct(
        public bool $success,
        public ?string $displaytext = null,
    ) {
    }
}
