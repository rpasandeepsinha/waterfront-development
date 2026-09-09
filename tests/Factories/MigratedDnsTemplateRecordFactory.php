<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Waterfront\Domain\Ferry\Models\MigratedDnsTemplateRecord;

/**
 * @extends Factory<MigratedDnsTemplateRecord>
 */
class MigratedDnsTemplateRecordFactory extends Factory
{
    protected $model = MigratedDnsTemplateRecord::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference_record_id' => $this->faker->word(),
        ];
    }
}
