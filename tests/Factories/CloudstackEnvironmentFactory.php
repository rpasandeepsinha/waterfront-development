<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Waterfront\Domain\VPS\Models\Environment;

/**
 * @extends Factory<Environment>
 */
class CloudstackEnvironmentFactory extends Factory
{
    protected $model = Environment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $words = $this->faker->words(2);
        assert(is_array($words));

        return [
            'name' => $this->faker->name(),
            'slug' => $this->faker->slug(),
            'api_url' => $this->faker->url(),
            'domain_id' => $this->faker->uuid(),
            'default_email_address' => $this->faker->email(),
            'default_role_id' => $this->faker->uuid(),
            'preferred' => true,
            'domain_name' => sprintf('%s/%s', ...$words),
            'ui_url' => $this->faker->url(),
            'api_key' => 'api_key',
            'secret_key' => 'secret_key',
        ];
    }
}
