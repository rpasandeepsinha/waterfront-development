<?php

declare(strict_types=1);

namespace Waterfront\Infra\AcronisClient\DTO\Responses\Users;

class UserAccessPolicies
{
    /**
     * @param PolicyItem[] $items
     */
    public function __construct(
        public array $items,
        public ?string $timestamp = null,
    ) {
    }
}
