<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Waterfront\Domain\DNS\Models\DnsExternalNameserver;

/**
 * @extends Factory<DnsExternalNameserver>
 */
class DnsExternalNameserverFactory extends Factory
{
    protected $model = DnsExternalNameserver::class;

    public function definition(): array
    {
        return [
            'nameserver' => $this->faker->unique()->regexify('ns[1-9]\.[a-z0-9]{6}\.external\.(com|net|org|nl|be)'),
            'ipv4' => $this->faker->ipv4(),
            'ipv6' => $this->faker->ipv6(),
        ];
    }
}
