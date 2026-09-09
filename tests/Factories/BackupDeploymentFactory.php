<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Ramsey\Uuid\Uuid;
use Waterfront\Domain\Provision\Backup\Models\BackupDeployment;

/**
 * @extends Factory<BackupDeployment>
 */
class BackupDeploymentFactory extends Factory
{
    protected $model = BackupDeployment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => Uuid::uuid4(),
            'origin_provisioning_request_id' => ProvisioningRequestFactory::new()->backup(),
        ];
    }
}
