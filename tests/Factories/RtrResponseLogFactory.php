<?php

declare(strict_types=1);

namespace Tests\Factories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Waterfront\Infra\RtrClient\Enums\RtrResponseSource;
use Waterfront\Infra\RtrClient\Models\RtrResponseLog;

/**
 * @extends Factory<RtrResponseLog>
 */
class RtrResponseLogFactory extends Factory
{
    protected $model = RtrResponseLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source' => RtrResponseSource::API_CALL,
            'response' => json_encode([]),
            'rtr_notification_id' => $this->faker->randomNumber(),
            'processed_at' => CarbonImmutable::now(),
            'failed_at' => null,
        ];
    }
}
