<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Waterfront\Domain\Customers\Models\MigratedCustomer;

/**
 * @extends Factory<MigratedCustomer>
 */
class MigratedCustomersFactory extends Factory
{
    protected $model = MigratedCustomer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference_customer_number' => $this->faker->creditCardNumber(),
            'reference_name'   => $this->faker->company(),
            'group_type' => $this->faker->randomAscii(),
            'successful' => false,
        ];
    }
}
