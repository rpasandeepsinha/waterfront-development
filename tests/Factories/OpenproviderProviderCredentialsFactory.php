<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Waterfront\Domain\Domains\Models\OpenproviderProviderCredentials;

/**
 * @extends Factory<OpenproviderProviderCredentials>
 */
class OpenproviderProviderCredentialsFactory extends Factory
{
    protected $model = OpenproviderProviderCredentials::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'api_url' => $this->faker->url(),
            'username' => $this->faker->userName(),
            'password' => $this->faker->password(),
        ];
    }
}
