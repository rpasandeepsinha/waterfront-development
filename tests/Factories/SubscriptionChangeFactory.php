<?php

declare(strict_types=1);

namespace Tests\Factories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Ramsey\Uuid\Uuid;
use Waterfront\Domain\Subscriptions\Enums\ProductChangeType;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionChangeStatus;
use Waterfront\Domain\Subscriptions\Models\SubscriptionChange;

/**
 * @extends Factory<SubscriptionChange>
 */
class SubscriptionChangeFactory extends Factory
{
    protected $model = SubscriptionChange::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => Uuid::uuid4(),
            'subscription_uuid' =>  Uuid::uuid4(),
            'from_product_uuid' => Uuid::uuid4(),
            'to_product_uuid' => Uuid::uuid4(),
            'status' => SubscriptionChangeStatus::COMPLETED,
            'type' => ProductChangeType::UPGRADE,
            'requested_at' => CarbonImmutable::now(),
            'completed_at' => CarbonImmutable::now(),
        ];
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => SubscriptionChangeStatus::EXECUTION_FAILED,
            'failure_code' => 500,
            'failure_message' => $this->faker->sentence(),
            'completed_at' => null,
        ]);
    }
}
