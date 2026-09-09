<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Ramsey\Uuid\Uuid;
use Waterfront\Domain\Provision\Hosting\Models\DirectAdminHostingDeployment;

/**
 * @extends Factory<DirectAdminHostingDeployment>
 */
class DirectAdminHostingDeploymentFactory extends Factory
{
    protected $model = DirectAdminHostingDeployment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => Uuid::uuid4()->toString(),
            'hosting_deployment_id' => ProvisionHostingDeploymentFactory::new()->withDirectAdminServer(),
            'username' => $this->faker->userName(),
            'default_domain' => $this->faker->domainName(),
        ];
    }
}
