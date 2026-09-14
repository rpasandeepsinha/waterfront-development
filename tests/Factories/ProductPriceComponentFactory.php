<?php

declare(strict_types=1);

namespace Tests\Factories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Pricing\Models\ProductPriceComponent;

/**
 * @extends Factory<ProductPriceComponent>
 */
class ProductPriceComponentFactory extends Factory
{
    protected $model = ProductPriceComponent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => null,
            'billing_period' => 12,
            'contract_period' => 12,
            'type' => $this->faker->randomElement(PriceComponentType::class),
            'price' => $this->faker->randomNumber(),
            'starts_at' => CarbonImmutable::now(),
            'expires_at' => null,
            'orderable' => true,
        ];
    }

    public function registration(): self
    {
        return $this->state(fn (): array => [
            'type' => PriceComponentType::REGISTRATION,
        ]);
    }

    public function prolongation(): self
    {
        return $this->state(fn (): array => [
            'type' => PriceComponentType::PROLONGATION,
        ]);
    }

    public function introduction(): self
    {
        return $this->state(fn (): array => [
            'type' => PriceComponentType::INTRODUCTION,
        ]);
    }

    public function registrationStaffel(): self
    {
        return $this->state(fn (): array => [
            'type' => PriceComponentType::REGISTRATION_STAFFEL,
        ]);
    }

    public function prolongationStaffel(): self
    {
        return $this->state(fn (): array => [
            'type' => PriceComponentType::PROLONGATION_STAFFEL,
        ]);
    }

    public function administrationFee(): self
    {
        return $this->registration()->state([
            'billing_period' => 1,
            'contract_period' => 1,
            'price' => 250,
            'orderable' => false,
        ]);
    }

    public function oneTimeService(): self
    {
        return $this->registration()->state([
            'billing_period' => 1,
            'contract_period' => 1,
            'price' => 2500,
            'orderable' => false,
        ]);
    }

    public function priceLadder(): self
    {
        return $this->state(fn (): array => [
            'type' => PriceComponentType::EXPERIMENT_PRICE_LADDER,
        ]);
    }
}
