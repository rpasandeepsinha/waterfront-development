<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Backup\Models;

use Illuminate\Database\Eloquent\Relations\HasOne;
use Waterfront\Domain\Provision\Models\ProvisionDeployment;

/**
 * @property ?AcronisBackupDeployment $acronisBackupDeployment
 */
class BackupDeployment extends ProvisionDeployment
{
    /**
     * @return HasOne<AcronisBackupDeployment, $this>
     */
    public function acronisBackupDeployment(): HasOne
    {
        return $this->hasOne(AcronisBackupDeployment::class);
    }
}
