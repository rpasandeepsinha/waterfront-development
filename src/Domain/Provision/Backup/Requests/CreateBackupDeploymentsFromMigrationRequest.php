<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Backup\Requests;

use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;

class CreateBackupDeploymentsFromMigrationRequest extends BackupProvisionRequest
{
    public ProvisionRequestName $name = ProvisionRequestName::CREATE_BACKUP_DEPLOYMENTS_FROM_MIGRATION;

    public protected(set) bool $requiresValidation = false;

    public function __construct(
        public int $acronisProviderId,
        public UuidInterface $tenantUuid,
        public UuidInterface $userUuid,
        UuidInterface $tag,
    ) {
        $this->tag = $tag;
    }
}
