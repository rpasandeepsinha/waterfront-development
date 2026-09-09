<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Models\ProductExperimentOfferings;

/**
 * @extends Factory<ProductExperimentOfferings>
 */
class ProductExperimentOfferingsFactory extends Factory
{
    protected $model = ProductExperimentOfferings::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
        ];
    }

    public function offeringFirstProduct(Product $product, bool $free): self
    {
        return $this->state(fn (): array => [
            'product_1_id'   => $product->id,
            'product_1_free' => $free,
        ]);
    }

    public function offeringSecondProduct(Product $product, bool $free): self
    {
        return $this->state(fn (): array => [
            'product_2_id'   => $product->id,
            'product_2_free' => $free,
        ]);
    }

    public function withCustomer(Customer $customer): self
    {
        return $this->afterCreating(function (ProductExperimentOfferings $offering) use ($customer): void {
            $offering->customers()->attach($customer->id);
        });
    }
}
