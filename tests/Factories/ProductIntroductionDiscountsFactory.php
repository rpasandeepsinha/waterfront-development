<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Waterfront\Domain\Pricing\Models\ProductIntroductionDiscount;

/**
 * @extends Factory<ProductIntroductionDiscount>
 */
class ProductIntroductionDiscountsFactory extends Factory
{
    protected $model = ProductIntroductionDiscount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => $this->faker->randomNumber(),
            'max_uses_per_customer' => $this->faker->randomNumber(),
            'contract_period' => 12,
        ];
    }
}
