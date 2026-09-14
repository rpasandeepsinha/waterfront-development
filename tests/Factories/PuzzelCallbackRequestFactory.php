<?php

declare(strict_types=1);

namespace Tests\Factories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Ramsey\Uuid\Uuid;
use Waterfront\Domain\Puzzel\Models\PuzzelCallbackRequest;

/**
 * @extends Factory<PuzzelCallbackRequest>
 */
class PuzzelCallbackRequestFactory extends Factory
{
    protected $model = PuzzelCallbackRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $timeslot = PuzzelCallbackTimeslotFactory::new()->createOne();

        $slotTime = $timeslot->start_timeslot;
        $desiredTime = CarbonImmutable::today()->setTime(
            hour: $slotTime->hour,
            minute: $slotTime->minute,
            second: $slotTime->second,
        );

        return [
            'uuid' => Uuid::uuid4(),
            'customer_id' => CustomerFactory::new(),
            'puzzel_callback_timeslot_id' => $timeslot->id,
            'phone_number' => $this->faker->e164PhoneNumber(),
            'name' => $this->faker->sentence(3),
            'request_category' => $this->faker->randomElement(['billing', 'technical_support']),
            'request_description' => $this->faker->sentence(10),
            'desired_callback_time' => $desiredTime,
        ];
    }
}
