<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Backup\Requests;

use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;

class TerminateBackupRequest extends BackupProvisionRequest
{
    public ProvisionRequestName $name = ProvisionRequestName::TERMINATE_BACKUP;

    public function __construct(
        public readonly UuidInterface $tagUuid,
    ) {
        $this->tag = $this->tagUuid;
    }
}
