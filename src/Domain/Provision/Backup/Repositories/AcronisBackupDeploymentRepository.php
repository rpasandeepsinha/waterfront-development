<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Backup\Repositories;

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Provision\Backup\Models\AcronisBackupDeployment;
use Waterfront\Domain\Provision\Backup\Models\BackupDeployment;

class AcronisBackupDeploymentRepository
{
    public function create(
        BackupDeployment $acronisDeployment,
        int $acronisProviderId,
        UuidInterface $tenant,
        UuidInterface $user,
    ): AcronisBackupDeployment {
        $acronisBackupDeployment = new AcronisBackupDeployment();
        $acronisBackupDeployment->uuid = Uuid::uuid4();
        $acronisBackupDeployment->acronis_provider_id = $acronisProviderId;
        $acronisBackupDeployment->backup_deployment_id = $acronisDeployment->id;
        $acronisBackupDeployment->tenant_uuid = $tenant;
        $acronisBackupDeployment->user_uuid = $user;
        $acronisBackupDeployment->save();

        return $acronisBackupDeployment;
    }

    public function findOrCreate(
        int $acronisDeploymentId,
        int $acronisProviderId,
        UuidInterface $tenant,
        UuidInterface $user,
    ): AcronisBackupDeployment {
        $acronisBackupDeployment = AcronisBackupDeployment::where([
            'acronis_provider_id' => $acronisProviderId,
            'backup_deployment_id' => $acronisDeploymentId,
            'tenant_uuid' => $tenant,
            'user_uuid' => $user,
        ])->first();

        if ($acronisBackupDeployment !== null) {
            return $acronisBackupDeployment;
        }

        $acronisBackupDeployment = new AcronisBackupDeployment();
        $acronisBackupDeployment->uuid = Uuid::uuid4();
        $acronisBackupDeployment->acronis_provider_id = $acronisProviderId;
        $acronisBackupDeployment->backup_deployment_id = $acronisDeploymentId;
        $acronisBackupDeployment->tenant_uuid = $tenant;
        $acronisBackupDeployment->user_uuid = $user;
        $acronisBackupDeployment->save();

        return $acronisBackupDeployment;
    }
}
