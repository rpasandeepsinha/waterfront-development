<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Ramsey\Uuid\Uuid;
use Waterfront\Domain\Provision\Hosting\Models\HostingDeployment;

/**
 * @extends Factory<HostingDeployment>
 */
class ProvisionHostingDeploymentFactory extends Factory
{
    protected $model = HostingDeployment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => Uuid::uuid4()->toString(),
            'origin_provisioning_request_id' => ProvisioningRequestFactory::new()->hosting(),
            'server_id' => ServerFactory::new(),
            'domain' => $this->faker->domainName(),
        ];
    }

    public function withPleskServer(): ProvisionHostingDeploymentFactory
    {
        return $this->state([
            'server_id' => ServerFactory::new()->plesk(),
        ]);
    }

    public function withDirectAdminServer(): ProvisionHostingDeploymentFactory
    {
        return $this->state([
            'server_id' => ServerFactory::new()->directadmin(),
        ]);
    }
}
