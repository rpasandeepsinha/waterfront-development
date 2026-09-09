<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Backup\Requests;

use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;

class SetBackupSuspensionStateRequest extends BackupProvisionRequest
{
    public ProvisionRequestName $name = ProvisionRequestName::SET_BACKUP_SUSPENSION_STATE;

    public function __construct(
        public readonly UuidInterface $tagUuid,
        public readonly bool $enable = false,
    ) {
        $this->tag = $this->tagUuid;
    }
}
