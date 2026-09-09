<?php

declare(strict_types=1);

namespace Tests\Factories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Waterfront\Domain\Subscriptions\Models\SubscriptionMutation;

/**
 * @extends Factory<SubscriptionMutation>
 */
class SubscriptionMutationFactory extends Factory
{
    protected $model = SubscriptionMutation::class;

    /** @return array<string, int> */
    public function definition(): array
    {
        return [
            'contract_period' => 24,
            'billing_period' => 24,
            'gross_price' => 120,
            'net_price' => 120,
        ];
    }

    public function mutated(CarbonImmutable $date): self
    {
        return $this->state(fn (): array => [
            'mutated_at' => $date,
        ]);
    }
}
