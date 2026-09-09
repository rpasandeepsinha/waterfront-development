<?php

declare(strict_types=1);

namespace Tests\Factories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Waterfront\Domain\Pricing\Models\SubscriptionPrice;

/**
 * @extends Factory<SubscriptionPrice>
 */
class SubscriptionPriceFactory extends Factory
{
    protected $model = SubscriptionPrice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'valid_from' => CarbonImmutable::now(),
            'net_price' => $this->faker->randomNumber(3),
        ];
    }
}
