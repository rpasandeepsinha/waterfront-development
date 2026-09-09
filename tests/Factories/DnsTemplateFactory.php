<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Waterfront\Domain\DNS\Models\DnsTemplate;

/**
 * @extends Factory<DnsTemplate>
 */
class DnsTemplateFactory extends Factory
{
    protected $model = DnsTemplate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => $this->faker->slug(),
        ];
    }
}
