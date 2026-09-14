<?php

declare(strict_types=1);

namespace Database\Seeders\Support;

use Carbon\CarbonImmutable;
use Database\Seeders\Scenarios\ScenarioReference;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Waterfront\Domain\Puzzel\Models\PuzzelCallbackTimeslot;

class PuzzelSeeder extends Seeder
{
    private const int CAPACITY = 5;

    public function __construct(
        private readonly ReferenceRepository $referenceRepository,
    ) {
    }

    public function run(): void
    {
        $timeslots = [
            ['09:00:00', '09:30:00', self::CAPACITY],
            ['09:30:00', '10:00:00', self::CAPACITY],
            ['10:00:00', '10:30:00', self::CAPACITY],
            ['10:30:00', '11:00:00', self::CAPACITY],
            ['11:00:00', '11:30:00', self::CAPACITY],
            ['11:30:00', '12:00:00', self::CAPACITY],
            ['13:00:00', '13:30:00', self::CAPACITY],
            ['13:30:00', '14:00:00', self::CAPACITY],
            ['14:00:00', '14:30:00', self::CAPACITY],
            ['14:30:00', '15:00:00', self::CAPACITY],
            ['15:00:00', '15:30:00', self::CAPACITY],
            ['15:30:00', '16:00:00', self::CAPACITY],
        ];

        foreach ($timeslots as $index => [$start, $end, $capacity]) {
            $timeslot = new PuzzelCallbackTimeslot();
            $timeslot->uuid = Str::uuid();
            $timeslot->capacity = $capacity;
            $timeslot->start_timeslot = CarbonImmutable::parse($start);
            $timeslot->end_timeslot = CarbonImmutable::parse($end);
            $timeslot->save();

            if ($index === 0) {
                $this->referenceRepository->set(
                    ScenarioReference::TEST_KEES_PUZZEL_FIRST_CALLBACK_TIMESLOT,
                    $timeslot,
                );
            }
        }
    }
}
