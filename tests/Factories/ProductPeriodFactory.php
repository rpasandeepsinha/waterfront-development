<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Waterfront\Domain\Products\Models\ProductPeriod;

/**
 * @extends Factory<ProductPeriod>
 */
class ProductPeriodFactory extends Factory
{
    protected $model = ProductPeriod::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contract_period' => 12,
            'billing_period' => 12,
        ];
    }
}
