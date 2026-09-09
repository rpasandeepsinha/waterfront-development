<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Waterfront\Domain\Customers\Models\CustomerVatError;

/**
 * @extends Factory<CustomerVatError>
 */
class CustomerVatFactory extends Factory
{
    protected $model = CustomerVatError::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'status_code' => $this->faker->numberBetween(400, 500),
            'message' => $this->faker->text(),
            'customer_id' => null,
        ];
    }
}
