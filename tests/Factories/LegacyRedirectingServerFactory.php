<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Waterfront\Domain\Servers\Models\LegacyRedirectingServer;

/**
 * @extends Factory<LegacyRedirectingServer>
 */
class LegacyRedirectingServerFactory extends Factory
{
    protected $model = LegacyRedirectingServer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'hostname' => $this->faker->domainName(),
            'ipv4' => $this->faker->ipv4(),
            'ipv6' => $this->faker->ipv6(),
            'original_business_unit' => $this->faker->word(),
        ];
    }
}
