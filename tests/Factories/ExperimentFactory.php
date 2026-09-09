<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Waterfront\Domain\Experiment\Enums\ExperimentType;
use Waterfront\Domain\Experiment\Models\Experiment;

/**
 * @extends Factory<Experiment>
 */
class ExperimentFactory extends Factory
{
    protected $model = Experiment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => $this->faker->randomElement(ExperimentType::class),
        ];
    }
}
