<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Waterfront\Domain\Subscriptions\Enums\ProductChangeType;
use Waterfront\Domain\Subscriptions\Models\ProductAllowedChange;

/**
 * @extends Factory<ProductAllowedChange>
 */
class ProductAllowedChangeFactory extends Factory
{
    protected $model = ProductAllowedChange::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'display_order' => 1,
            'change_type' => ProductChangeType::UPGRADE,
            'is_available_for_customer' => true,
        ];
    }

    public function upgradeChange(): ProductAllowedChangeFactory
    {
        return $this->state([
            'change_type' => ProductChangeType::UPGRADE,
        ]);
    }

    public function downgradeChange(): ProductAllowedChangeFactory
    {
        return $this->state([
            'change_type' => ProductChangeType::DOWNGRADE,
        ]);
    }

    public function upgradeChangeSupportOnly(): ProductAllowedChangeFactory
    {
        return $this->state([
            'change_type' => ProductChangeType::UPGRADE,
            'is_available_for_customer' => false,
        ]);
    }

    public function downgradeChangeSupportOnly(): ProductAllowedChangeFactory
    {
        return $this->state([
            'change_type' => ProductChangeType::DOWNGRADE,
            'is_available_for_customer' => false,
        ]);
    }
}
