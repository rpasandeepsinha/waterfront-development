<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Waterfront\Domain\Domains\Models\DomainContact;

/**
 * @extends Factory<DomainContact>
 */
class DomainContactFactory extends Factory
{
    protected $model = DomainContact::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => 1,
            'default_owner' => false,
            'email' => $this->faker->email(),
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'phone_country_code' => '+31',
            'phone_area_code' => '115',
            'phone_subscriber_number' => '123456',
            'street_name' => $this->faker->streetName(),
            'street_number' => $this->faker->buildingNumber(),
            'zip_code' => $this->faker->postcode(),
            'city' => $this->faker->city(),
            'country_code' => $this->faker->countryCode(),
        ];
    }
}
