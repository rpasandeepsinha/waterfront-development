<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Ramsey\Uuid\Uuid;
use Waterfront\Domain\Provision\Hosting\Models\PleskHostingDeployment;

/**
 * @extends Factory<PleskHostingDeployment>
 */
class PleskHostingDeploymentFactory extends Factory
{
    protected $model = PleskHostingDeployment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => Uuid::uuid4()->toString(),
            'hosting_deployment_id' => ProvisionHostingDeploymentFactory::new()->withPleskServer(),
            'customer_name' => $this->faker->userName(),
            'subscription_domain' => $this->faker->domainName(),
        ];
    }
}
