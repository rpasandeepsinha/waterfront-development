<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Dto\Backup;

class BackupMigrationPayload
{
    public function __construct(
        public string $referenceSubscriptionId,
        public string $buTenantUuid,
        public string $customerTenantUuid,
        public string $userUuid,
    ) {
    }
}
