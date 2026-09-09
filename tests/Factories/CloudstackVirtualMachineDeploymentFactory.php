<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Waterfront\Domain\VPS\Models\VirtualMachineDeployment;

/**
 * @extends Factory<VirtualMachineDeployment>
 */
class CloudstackVirtualMachineDeploymentFactory extends Factory
{
    protected $model = VirtualMachineDeployment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subscription_uuid' => $this->faker->uuid(),
            'manager_domain_deployment_id' => CloudstackManagerDomainDeploymentFactory::new(),
            'cloudstack_id' => $this->faker->uuid(),
            'custom_name' => $this->faker->name(),
        ];
    }
}
