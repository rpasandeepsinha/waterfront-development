<?php

declare(strict_types=1);

namespace Tests\Factories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Ramsey\Uuid\Uuid;
use Waterfront\Domain\Products\Enums\ProductPromotionPlatform;
use Waterfront\Domain\Products\Models\ProductPromotion;

/**
 * @extends Factory<ProductPromotion>
 */
class ProductPromotionFactory extends Factory
{
    protected $model = ProductPromotion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = new CarbonImmutable();

        return [
            'uuid' => Uuid::uuid4(),
            'platform' => ProductPromotionPlatform::CUSTOMER_PANEL,
            'placement_url' => $this->faker->url(),
            'start_date' => $startDate,
            'end_date' => $startDate->addYear(),
            'weight' => 1,
            'call_to_action' => [
                'title' => $this->faker->sentence(),
                'button_text' => $this->faker->words(2, true),
                'description' => $this->faker->sentence(),
                'destination_url' => $this->faker->url(),
                'price_description' => $this->faker->sentence(),
            ],
        ];
    }
}
