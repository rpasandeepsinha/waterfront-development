<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Models\ProductSpec;

/**
 * @extends Factory<ProductSpec>
 */
class ProductSpecFactory extends Factory
{
    protected $model = ProductSpec::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'test',
            'value' => 'wow',
        ];
    }

    public function enable(ProductSpecName $name): self
    {
        return $this->state([
            'name' => $name,
            'value' => true,
        ]);
    }

    public function disable(ProductSpecName $name): self
    {
        return $this->state([
            'name' => $name,
            'value' => false,
        ]);
    }
}
