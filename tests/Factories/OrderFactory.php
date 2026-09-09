<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Waterfront\Domain\Orders\Enums\OrderStatus;
use Waterfront\Domain\Orders\Models\Order;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $administrationFee = $this->faker->randomNumber();

        return [
            'uuid' => $this->faker->uuid(),
            'status' => OrderStatus::IN_PROGRESS,
            'total_price' => $this->faker->randomNumber() + $administrationFee,
            'administration_fees' => $administrationFee,
        ];
    }
}
