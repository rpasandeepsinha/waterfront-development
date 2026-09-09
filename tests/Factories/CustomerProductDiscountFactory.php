<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Waterfront\Domain\Customers\Models\CustomerProductDiscount;

/**
 * @extends Factory<CustomerProductDiscount>
 */
class CustomerProductDiscountFactory extends Factory
{
    protected $model = CustomerProductDiscount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [];
    }
}
