<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Waterfront\Domain\Voucher\Models\VoucherClaim;

/**
 * @extends Factory<VoucherClaim>
 */
class VoucherClaimFactory extends Factory
{
    protected $model = VoucherClaim::class;

    /**
     * @inheritDoc
     */
    public function definition(): array
    {
        return [
            'amount_claimed' => $this->faker->randomNumber(),
        ];
    }
}
