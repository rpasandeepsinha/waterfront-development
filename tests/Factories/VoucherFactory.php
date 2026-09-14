<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Waterfront\Domain\Voucher\Enum\VoucherAmountType;
use Waterfront\Domain\Voucher\Models\Voucher;

/**
 * @extends Factory<Voucher>
 */
class VoucherFactory extends Factory
{
    protected $model = Voucher::class;

    /**
     * @inheritDoc
     */
    public function definition(): array
    {
        return [
            'display_name' => $this->faker->sentence(2),
            'internal_name' => $this->faker->sentence(2),
            'description' => $this->faker->text(),
            'code' => $this->faker->text(15),
            // @phpstan-ignore property.nonObject (randomElement typehints its return type as mixed, so it doesn't understand the ->value on it)
            'amount_type' => $this->faker->randomElement(VoucherAmountType::cases())->value,
            'amount' => $this->faker->numberBetween(1, 100),
            'max_claims' => $this->faker->numberBetween(1, 99),
            'billing_period' => null,
            'contract_period' => null,
            'expiration_date' => null,
            'apply_with_discount' => $this->faker->boolean(),
            'allow_multiple_claims_same_customer' => $this->faker->boolean(),
        ];
    }
}
