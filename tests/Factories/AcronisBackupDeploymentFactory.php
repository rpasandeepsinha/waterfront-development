<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Ramsey\Uuid\Uuid;
use Waterfront\Domain\Provision\Backup\Models\AcronisBackupDeployment;

/**
 * @extends Factory<AcronisBackupDeployment>
 */
class AcronisBackupDeploymentFactory extends Factory
{
    protected $model = AcronisBackupDeployment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => Uuid::uuid4(),
            'backup_deployment_id' => BackupDeploymentFactory::new(),
            'acronis_provider_id' => AcronisProviderFactory::new(),
            'tenant_uuid' => Uuid::uuid4(),
            'user_uuid' => Uuid::uuid4(),
        ];
    }
}
