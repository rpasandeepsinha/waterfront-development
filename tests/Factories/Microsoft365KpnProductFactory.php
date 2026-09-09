<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Waterfront\Domain\Microsoft365\Models\Microsoft365KpnProduct;

/**
 * @extends Factory<Microsoft365KpnProduct>
 */
class Microsoft365KpnProductFactory extends Factory
{
    protected $model = Microsoft365KpnProduct::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contract_period' => $this->faker->randomElement([1, 12]),
            'kpn_product_code' => $this->faker->randomNumber(),
        ];
    }
}
