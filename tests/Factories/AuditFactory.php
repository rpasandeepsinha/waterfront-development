<?php

declare(strict_types=1);

namespace Tests\Factories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Waterfront\Domain\History\Models\Audit;

/**
 * @extends Factory<Audit>
 */
class AuditFactory extends Factory
{
    protected $model = Audit::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // You need to fill in auditable_type, auditable_id, user_type , user_id, old_values & new_values yourself.
        return [
            'old_values' => [],
            'new_values' => [],
            'event' => 'created',
            'url' => $this->faker->url(),
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'tags' => null,
            'created_at' => CarbonImmutable::now(),
            'updated_at' => CarbonImmutable::now(),
        ];
    }
}
