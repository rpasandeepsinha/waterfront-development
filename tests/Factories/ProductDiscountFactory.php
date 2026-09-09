<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Waterfront\Domain\Products\Models\ProductDiscount;

/**
 * @extends Factory<ProductDiscount>
 */
class ProductDiscountFactory extends Factory
{
    protected $model = ProductDiscount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'description' => $this->faker->text(),
        ];
    }
}
