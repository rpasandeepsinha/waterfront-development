<?php

declare(strict_types=1);

namespace Waterfront\Domain\Cart\DTO;

class MetaData
{
    public function __construct(
        public ?string $domain,
        public ?string $transferCode,
        public ?int $contactHandle,
        public ?string $microsoft365TenantName,
        public ?string $microsoft365TenantId,
        public ?string $sshKeyUuid,
        public ?string $experimentSlug,
    ) {
    }
}
