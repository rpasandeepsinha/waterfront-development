<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Waterfront\Domain\DNS\Models\DnsRegion;

/**
 * @extends Factory<DnsRegion>
 */
class DnsRegionFactory extends Factory
{
    protected $model = DnsRegion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->city(),
        ];
    }
}
