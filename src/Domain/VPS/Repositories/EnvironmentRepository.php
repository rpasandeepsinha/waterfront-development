<?php

declare(strict_types=1);

namespace Waterfront\Domain\VPS\Repositories;

use Waterfront\Domain\VPS\Models\Environment;

class EnvironmentRepository
{
    public function getPreferredEnvironment(): ?Environment
    {
        return Environment::query()->orderBy('preferred', 'desc')->first();
    }
}
