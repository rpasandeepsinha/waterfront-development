<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Models\ProductGroup;

/**
 * @extends Factory<ProductGroup>
 */
class ProductGroupFactory extends Factory
{
    protected $model = ProductGroup::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'ledger_code' => $this->faker->randomNumber(),
            'default_billing_period' => 12,
            'default_contract_period' => 12,
            'slug' => $this->faker->randomElement(ProductGroupType::class),
        ];
    }

    public function extension(): ProductGroupFactory
    {
        return $this->state(fn (array $attributes): array => [
            'name' => ProductGroupType::EXTENSION,
            'slug' => ProductGroupType::EXTENSION,
        ]);
    }

    public function redirect(): ProductGroupFactory
    {
        return $this->state(fn (array $attributes): array => [
            'slug' => ProductGroupType::REDIRECT,
        ]);
    }

    public function hosting(): ProductGroupFactory
    {
        return $this->state(fn (array $attributes): array => [
            'name' => 'hosting',
            'slug' => ProductGroupType::HOSTING,
        ]);
    }

    public function ssl(): ProductGroupFactory
    {
        return $this->state(fn (array $attributes): array => [
            'slug' => ProductGroupType::SSL,
        ]);
    }

    public function dns(): ProductGroupFactory
    {
        return $this->state(fn (array $attributes): array => [
            'slug' => ProductGroupType::DNS,
        ]);
    }

    public function cloudstackVirtualMachine(): ProductGroupFactory
    {
        return $this->state(fn (array $attributes): array => [
            'slug' => ProductGroupType::CLOUDSTACK_VIRTUAL_MACHINE,
        ]);
    }

    public function cloudstackVolume(): ProductGroupFactory
    {
        return $this->state(fn (array $attributes): array => [
            'slug' => ProductGroupType::CLOUDSTACK_VOLUME,
        ]);
    }

    public function cloudstackOs(): ProductGroupFactory
    {
        return $this->state(fn (array $attributes): array => [
            'slug' => ProductGroupType::CLOUDSTACK_OS,
        ]);
    }

    public function microsoft365(): ProductGroupFactory
    {
        return $this->state(fn (array $attributes): array => [
            'slug' => ProductGroupType::MICROSOFT_365,
        ]);
    }

    public function manualSubscription(): ProductGroupFactory
    {
        return $this->state(fn (array $attributes): array => [
            'slug' => ProductGroupType::MANUAL_SUBSCRIPTION,
        ]);
    }

    public function addon(): ProductGroupFactory
    {
        return $this->state(fn (array $attributes): array => [
            'slug' => ProductGroupType::ADD_ON,
        ]);
    }

    public function resellerHosting(): ProductGroupFactory
    {
        return $this->state(fn (array $attributes): array => [
            'slug' => ProductGroupType::RESELLER_HOSTING,
        ]);
    }

    public function vps(): ProductGroupFactory
    {
        return $this->state(fn (array $attributes): array => [
            'slug' => ProductGroupType::VPS,
        ]);
    }

    public function other(): ProductGroupFactory
    {
        return $this->state(fn (array $attributes): array => [
            'slug' => ProductGroupType::OTHER,
        ]);
    }

    public function oneTimeService(): ProductGroupFactory
    {
        return $this->state(fn (array $attributes): array => [
            'slug' => ProductGroupType::ONE_TIME_SERVICE,
            'name' => ProductGroupType::ONE_TIME_SERVICE,
        ]);
    }

    public function volumeDiscount(): ProductGroupFactory
    {
        return $this->state(fn (array $attributes): array => [
            'slug' => ProductGroupType::VOLUME_DISCOUNT,
            'name' => ProductGroupType::VOLUME_DISCOUNT,
        ]);
    }

    public function backup(): ProductGroupFactory
    {
        return $this->state(fn (array $attributes): array => [
            'slug' => ProductGroupType::BACKUP,
        ]);
    }
}
