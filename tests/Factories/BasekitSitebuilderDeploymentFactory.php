<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Ramsey\Uuid\Uuid;
use Waterfront\Domain\Provision\Sitebuilder\Models\BasekitSitebuilderDeployment;

/**
 * @extends Factory<BasekitSitebuilderDeployment>
 */
class BasekitSitebuilderDeploymentFactory extends Factory
{
    protected $model = BasekitSitebuilderDeployment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => Uuid::uuid4()->toString(),
            'sitebuilder_deployment_id' => SitebuilderDeploymentFactory::new(),
            'site_ref' => $this->faker->numberBetween(1, 2000),
        ];
    }
}
