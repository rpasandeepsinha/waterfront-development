<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Waterfront\Domain\VPS\Models\EnvironmentProduct;

/**
 * @extends Factory<EnvironmentProduct>
 */
class CloudstackEnvironmentProductFactory extends Factory
{
    protected $model = EnvironmentProduct::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'environment_id' => $this->faker->randomNumber(),
            'product_identifier' => $this->faker->uuid(),
        ];
    }
}
