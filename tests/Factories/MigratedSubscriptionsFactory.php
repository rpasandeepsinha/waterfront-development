<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Waterfront\Domain\Customers\Models\MigratedSubscription;

/**
 * @extends Factory<MigratedSubscription>
 */
class MigratedSubscriptionsFactory extends Factory
{
    protected $model = MigratedSubscription::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference_product_id' => 'product_id_from_bu',
            'reference_subscription_id' => 'subscription_id_from_bu',
        ];
    }
}
