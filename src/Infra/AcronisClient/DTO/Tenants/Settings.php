<?php

declare(strict_types=1);

namespace Waterfront\Infra\AcronisClient\DTO\Tenants;

use Symfony\Component\Serializer\Attribute\Groups;

#[Groups(['create'])]
class Settings
{
    public function __construct(
        public bool $enhancedSecurity,
    ) {
    }
}
