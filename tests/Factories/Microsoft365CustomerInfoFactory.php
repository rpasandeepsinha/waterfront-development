<?php

declare(strict_types=1);

namespace Tests\Factories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Waterfront\Domain\Microsoft365\Enums\CustomerInfoType;
use Waterfront\Domain\Microsoft365\Enums\Microsoft365ProcessStatus;
use Waterfront\Domain\Microsoft365\Models\Microsoft365CustomerInfo;

/**
 * @extends Factory<Microsoft365CustomerInfo>
 */
class Microsoft365CustomerInfoFactory extends Factory
{
    protected $model = Microsoft365CustomerInfo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => CustomerInfoType::REGISTER,
            'customer_id' => $this->faker->randomNumber(),
            'kpn_customer_id' => 'CID' . $this->faker->unique()->randomNumber(),
            'technical_status' => Microsoft365ProcessStatus::ACTIVE,
            'tenant_order_id' => $this->faker->randomNumber(8, true),
            'tenant_name' => $this->faker->name(),
            'tenant_id' => $this->faker->uuid(),
            'tenant_access_verified' => $this->faker->boolean(),
            'mca_signed_at' => CarbonImmutable::now(),
        ];
    }
}
