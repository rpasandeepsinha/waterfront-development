<?php

declare(strict_types=1);

namespace Tests\Factories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Waterfront\Domain\Puzzel\Models\PuzzelBlockedDate;

/**
 * @extends Factory<PuzzelBlockedDate>
 */
class PuzzelBlockedDateFactory extends Factory
{
    protected $model = PuzzelBlockedDate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => CarbonImmutable::now(),
            'reason' => $this->faker->sentence(random_int(3, 6)),
        ];
    }
}
