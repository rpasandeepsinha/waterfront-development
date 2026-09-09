<?php

declare(strict_types=1);

namespace Waterfront\Infra\AcronisClient\DTO\Tenants;

class TenantUsages
{
    /**
     * @param list<TenantUsage> $items
     */
    public function __construct(
        public array $items,
    ) {
    }
}
