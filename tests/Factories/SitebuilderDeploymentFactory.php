<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Ramsey\Uuid\Uuid;
use Waterfront\Domain\Provision\Sitebuilder\Models\SitebuilderDeployment;

/**
 * @extends Factory<SitebuilderDeployment>
 */
class SitebuilderDeploymentFactory extends Factory
{
    protected $model = SitebuilderDeployment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => Uuid::uuid4()->toString(),
            'origin_provisioning_request_id' => ProvisioningRequestFactory::new()->sitebuilder(),
            'domain' => $this->faker->domainName(),
        ];
    }
}
