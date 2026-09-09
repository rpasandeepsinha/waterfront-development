<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Waterfront\Domain\Ferry\Models\FerryInternalNameserver;

/**
 * @extends Factory<FerryInternalNameserver>
 */
class FerryInternalNameserverFactory extends Factory
{
    protected $model = FerryInternalNameserver::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nameserver_hostname' => $this->faker->unique()->regexify('ns[1-9]\.[a-z0-9]{6}\.ferry\.internal'),
        ];
    }
}
