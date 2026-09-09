<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Backup\Requests;

use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;

class GetBackupUsageRequest extends BackupProvisionRequest
{
    public ProvisionRequestName $name = ProvisionRequestName::GET_BACKUP_USAGE;

    public function __construct(
        public UuidInterface $tagUuid,
    ) {
        $this->tag = $this->tagUuid;
    }
}
