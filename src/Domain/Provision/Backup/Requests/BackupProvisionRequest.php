<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Backup\Requests;

use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\Requests\ProvisionRequest;

abstract class BackupProvisionRequest extends ProvisionRequest
{
    public ProvisionType $type = ProvisionType::BACKUP;
}
