<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Waterfront\Domain\VPS\Models\ManagerDomainDeployment;

/**
 * @extends Factory<ManagerDomainDeployment>
 */
class CloudstackManagerDomainDeploymentFactory extends Factory
{
    protected $model = ManagerDomainDeployment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => CustomerFactory::new(),
            'environment_id' => CloudstackEnvironmentFactory::new(),
            'domain_id' => $this->faker->uuid(),
            'domain_name' => $this->faker->userName(),
            'account' => $this->faker->userName(),
            'username' => $this->faker->userName(),
        ];
    }
}
