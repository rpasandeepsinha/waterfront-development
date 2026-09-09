<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Waterfront\Domain\Hosting\Models\SpamExpertsCluster;

/**
 * @extends Factory<SpamExpertsCluster>
 */
class SpamExpertsClusterFactory extends Factory
{
    protected $model = SpamExpertsCluster::class;

    public function definition(): array
    {
        return [
            'hostname' => $this->faker->url(),
            'username' => $this->faker->userName(),
            'password' => $this->faker->password(),
            'ssl' => true,
        ];
    }
}
