<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Waterfront\Domain\DNS\Models\DnsVanityNameserver;

/**
 * @extends Factory<DnsVanityNameserver>
 */
class DnsVanityNameserverFactory extends Factory
{
    protected $model = DnsVanityNameserver::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nameserver' => $this->faker->unique()->regexify('ns[1-9]\.[a-z0-9]{6}\.vanity\.(com|net|org|nl|be)'),
        ];
    }
}
