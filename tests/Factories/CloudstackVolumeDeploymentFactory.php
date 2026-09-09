<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Waterfront\Domain\VPS\Models\VolumeDeployment;

/**
 * @extends Factory<VolumeDeployment>
 */
class CloudstackVolumeDeploymentFactory extends Factory
{
    protected $model = VolumeDeployment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cloudstack_id' => $this->faker->uuid(),
        ];
    }
}
