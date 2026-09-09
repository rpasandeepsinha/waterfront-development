<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Waterfront\Domain\Domains\Models\DomainProviderBusinessUnit;

/**
 * @extends Factory<DomainProviderBusinessUnit>
 */
class DomainProviderBusinessUnitFactory extends Factory
{
    protected $model = DomainProviderBusinessUnit::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => $this->faker->unique()->slug(),
            'name' => $this->faker->word(),
        ];
    }

    public function waterfront(): self
    {
        return $this->state(fn (): array => [
            'slug' => 'waterfront',
            'name' => 'Waterfront',
        ]);
    }

    public function argeweb(): self
    {
        return $this->state(fn (): array => [
            'slug' => 'argeweb',
            'name' => 'Argeweb',
        ]);
    }
}
