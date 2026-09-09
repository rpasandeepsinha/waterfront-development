<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Ramsey\Uuid\Uuid;
use Waterfront\Domain\Provision\Backup\Acronis\Models\AcronisProvider;

/**
 * @extends Factory<AcronisProvider>
 */
class AcronisProviderFactory extends Factory
{
    protected $model = AcronisProvider::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => Uuid::uuid4(),
            'name' => $this->faker->company(),
            'endpoint' => rtrim($this->faker->url(), '/'),
            'tenant_uuid' => Uuid::uuid4(),
            'client_id' => Uuid::uuid4(),
            'client_secret' => $this->faker->regexify('[a-z0-9]'),
            'default' => false,
            'sso_target_url' => rtrim($this->faker->url(), '/'),
        ];
    }

    public function default(): self
    {
        return $this->state(fn (): array => [
            'default' => true,
        ]);
    }
}
