<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Ramsey\Uuid\Uuid;
use Waterfront\Domain\ResellerHosting\Models\ResellerHostingDeployment;

/**
 * @extends Factory<ResellerHostingDeployment>
 */
class ResellerHostingDeploymentFactory extends Factory
{
    protected $model = ResellerHostingDeployment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plesk_customer_username' => $this->faker->name(),
            'plesk_customer_id' => $this->faker->randomNumber(),
            'directadmin_customer_username' => $this->faker->name(),
            'server_id'         => new ServerFactory()->directadmin(),
            'subscription_uuid' => Uuid::uuid4(),
            'storage_type' => 'ssd',
            'disk_space' => $this->faker->randomNumber(),
            'max_email_addresses' => $this->faker->randomNumber(),
            'max_traffic' => $this->faker->randomNumber(),
            'max_databases' => $this->faker->randomNumber(),
            'max_users' => $this->faker->randomNumber(),
            'max_domains' => $this->faker->randomNumber(),
        ];
    }
}
