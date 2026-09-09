<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Waterfront\Domain\Customers\Models\CustomerWallet;

/**
 * @extends Factory<CustomerWallet>
 */
class CustomerWalletFactory extends Factory
{
    protected $model = CustomerWallet::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => $this->faker->randomNumber(),
            'amount' => $this->faker->numberBetween(0, 12345),
            'bank_account_number' => $this->faker->iban(),
            'bank_account_name' => $this->faker->name(),
            'refund_requested_at' => null,
            'csv_downloaded_at' => null,
        ];
    }
}
