<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Waterfront\Domain\Ferry\Models\MigratedDnsTemplate;

/**
 * @extends Factory<MigratedDnsTemplate>
 */
class MigratedDnsTemplateFactory extends Factory
{
    protected $model = MigratedDnsTemplate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference_template_id' => $this->faker->word(),
        ];
    }
}
