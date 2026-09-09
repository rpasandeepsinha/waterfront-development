<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Waterfront\Domain\Products\Models\ProductAddonCoupling;

/**
 * @extends Factory<ProductAddonCoupling>
 */
class ProductAddonCouplingFactory extends Factory
{
    protected $model = ProductAddonCoupling::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'parent_product_id' => $this->faker->randomNumber(),
            'addon_product_id' => $this->faker->randomNumber(),
        ];
    }
}
