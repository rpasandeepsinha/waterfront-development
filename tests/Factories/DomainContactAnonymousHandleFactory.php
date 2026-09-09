<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Waterfront\Domain\Domains\Models\DomainContactAnonymousHandle;

/**
 * @extends Factory<DomainContactAnonymousHandle>
 */
class DomainContactAnonymousHandleFactory extends Factory
{
    protected $model = DomainContactAnonymousHandle::class;

    /**
     * @return array<string, string>
     */
    public function definition(): array
    {
        return [
            'handle' => $this->faker->unique()->slug(),
            'original_business_unit' => $this->faker->slug(),
        ];
    }
}
