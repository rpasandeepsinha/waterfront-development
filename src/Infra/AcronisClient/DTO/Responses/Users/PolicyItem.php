<?php

declare(strict_types=1);

namespace Waterfront\Infra\AcronisClient\DTO\Responses\Users;

use Waterfront\Infra\AcronisClient\Enums\RoleId;
use Waterfront\Infra\AcronisClient\Enums\TrusteeType;

class PolicyItem
{
    public ?string $id = null;

    public ?string $createdAt = null;

    public ?string $updatedAt = null;

    public ?string $deletedAt = null;

    public ?string $issuerId = null;

    public ?string $resourceServerId = null;

    public ?string $resourceNamespace = null;

    public ?string $resourcePath = null;

    public function __construct(
        public string $tenantId,
        public string $trusteeId,
        public TrusteeType $trusteeType,
        public RoleId $roleId,
        public float|int|string $version,
    ) {
    }
}
