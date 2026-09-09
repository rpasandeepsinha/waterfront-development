<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Waterfront\Domain\DNS\Models\DnsCustomerTemplate;

/**
 * @extends Factory<DnsCustomerTemplate>
 */
class DnsCustomerTemplateFactory extends Factory
{
    protected $model = DnsCustomerTemplate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->word(),
        ];
    }
}
