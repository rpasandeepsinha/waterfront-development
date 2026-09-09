<?php

declare(strict_types=1);

namespace Waterfront\Infra\AcronisClient\DTO\Responses\Users;

class TenantUsers
{
    /**
     * @param string[] $items
     */
    public function __construct(
        public array $items,
    ) {
    }
}
