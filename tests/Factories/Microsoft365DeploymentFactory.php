<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Waterfront\Domain\Microsoft365\Enums\Microsoft365OrderStatus;
use Waterfront\Domain\Microsoft365\Models\Microsoft365Deployment;

/**
 * @extends Factory<Microsoft365Deployment>
 */
class Microsoft365DeploymentFactory extends Factory
{
    protected $model = Microsoft365Deployment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subscription_id' => $this->faker->unique()->randomNumber(),
            'kpn_status' => Microsoft365OrderStatus::ACTIVE,
            'kpn_order_id' => $this->faker->unique()->randomNumber(),
            'microsoft365_customer_info_id' => $this->faker->unique()->randomNumber(),
        ];
    }
}
