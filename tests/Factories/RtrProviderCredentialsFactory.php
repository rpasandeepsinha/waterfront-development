<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Waterfront\Domain\Domains\Models\RtrProviderCredentials;

/**
 * @extends Factory<RtrProviderCredentials>
 */
class RtrProviderCredentialsFactory extends Factory
{
    protected $model = RtrProviderCredentials::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'api_url' => $this->faker->url(),
            'api_key' => $this->faker->password(32),
            'handle' => $this->faker->slug(),
        ];
    }
}
