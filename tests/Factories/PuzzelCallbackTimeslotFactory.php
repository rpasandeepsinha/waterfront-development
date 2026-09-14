<?php

declare(strict_types=1);

namespace Tests\Factories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Ramsey\Uuid\Uuid;
use Waterfront\Domain\Puzzel\Models\PuzzelCallbackTimeslot;

/**
 * @extends Factory<PuzzelCallbackTimeslot>
 */
class PuzzelCallbackTimeslotFactory extends Factory
{
    protected $model = PuzzelCallbackTimeslot::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $base = CarbonImmutable::today()->setTime(
            hour: 9,
            minute: 0,
            second: 0,
        );
        $slot = $this->faker->numberBetween(0, 11);

        $start = $base->addMinutes($slot * 30);
        $end = $start->addMinutes(30);

        return [
            'uuid' => Uuid::uuid4(),
            'capacity' => $this->faker->numberBetween(3, 10),
            'start_timeslot' => $start->format('H:i:s'),
            'end_timeslot' => $end->format('H:i:s'),
        ];
    }
}
