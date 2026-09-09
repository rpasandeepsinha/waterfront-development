<?php

declare(strict_types=1);

namespace Tests\Factories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Ramsey\Uuid\Uuid;
use Waterfront\Domain\OneTimeServices\Enums\OneTimeServiceStatus;
use Waterfront\Domain\OneTimeServices\Models\OneTimeService;

/**
 * @extends Factory<OneTimeService>
 */
class OneTimeServiceFactory extends Factory
{
    protected $model = OneTimeService::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => Uuid::uuid4(),
            'amount' => 1,
            'discount_percentage' => 0,
            'gross_price' => 2500,
            'execution_date' => CarbonImmutable::now(),
            'status' => OneTimeServiceStatus::OPEN,
        ];
    }
}
