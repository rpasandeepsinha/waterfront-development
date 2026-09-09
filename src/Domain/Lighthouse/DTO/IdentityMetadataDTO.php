<?php

declare(strict_types=1);

namespace Waterfront\Domain\Lighthouse\DTO;

use Ramsey\Uuid\UuidInterface;

class IdentityMetadataDTO
{
    public function __construct(
        public UuidInterface $uuid,
        public string $email,
    ) {
    }
}
