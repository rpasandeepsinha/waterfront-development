<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Pricing\Models\SubscriptionPriceComponent;

/**
 * @extends Factory<SubscriptionPriceComponent>
 */
class SubscriptionPriceComponentFactory extends Factory
{
    protected $model = SubscriptionPriceComponent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $price = $this->faker->randomNumber(3);

        // The table checks that at least one of the discount/price columns is filled in.
        return [
            'type' => PriceComponentType::REGISTRATION,
            'percentage_discount' => null,
            'fixed_discount' => null,
            'fixed_price' => $price,
            'new_price' => $price,
            'order_applied' => 1,
        ];
    }
}
