<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Waterfront\Domain\Domains\Models\OpenSrsProviderCredentials;

/**
 * @extends Factory<OpenSrsProviderCredentials>
 */
class OpenSrsProviderCredentialsFactory extends Factory
{
    protected $model = OpenSrsProviderCredentials::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'api_url' => $this->faker->url(),
            'username' => $this->faker->userName(),
            'api_key' => $this->faker->sha1(),
        ];
    }
}
