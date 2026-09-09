<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Waterfront\Domain\Payments\Models\MollieCustomer;

/**
 * @extends Factory<MollieCustomer>
 */
class MollieCustomerFactory extends Factory
{
    protected $model = MollieCustomer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mollie_customer_reference_id' => $this->faker->text(8),
        ];
    }

    /**
     * @param array<mixed> $data
     */
    public function withMandate(array $data): self
    {
        return $this->afterCreating(function (MollieCustomer $mollieCustomer) use ($data): void {
            new MandateFactory()->for($mollieCustomer)->createOne($data);
        });
    }
}
