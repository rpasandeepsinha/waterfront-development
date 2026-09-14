<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Waterfront\Domain\Customers\Models\CustomerAddress;

/**
 * @extends Factory<CustomerAddress>
 */
class CustomerAddressFactory extends Factory
{
    protected $model = CustomerAddress::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'street_name' => $this->faker->streetName(),
            'street_number' => $this->faker->numberBetween(1, 100),
            'street_number_addition' => $this->faker
                ->optional(0.1)
                ->lexify(str_repeat('?', $this->faker->numberBetween(1, 9))),
            'zip_code' => '1234 AB',
            'city' => $this->faker->city(),
            'country_code' => 'NL',
        ];
    }
}
