<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Backup\Requests;

use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;

class GetBackupSsoRequest extends BackupProvisionRequest
{
    public ProvisionRequestName $name = ProvisionRequestName::GET_BACKUP_SSO_REQUEST;

    public function __construct(
        public UuidInterface $tagUuid,
    ) {
        $this->tag = $this->tagUuid;
    }
}
