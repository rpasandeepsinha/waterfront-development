<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Waterfront\Domain\Payments\Models\Mandate;
use Waterfront\Infra\MollieClient\Enums\MollieMandateMethod;

/**
 * @extends Factory<Mandate>
 */
class MandateFactory extends Factory
{
    protected $model = Mandate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mollie_mandate_reference_id' => $this->faker->text(8),
            'method' => MollieMandateMethod::PAYPAL,
            'signature_date' => $this->faker->date(),
        ];
    }
}
