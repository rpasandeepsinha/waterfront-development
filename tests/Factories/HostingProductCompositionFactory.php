<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Waterfront\Domain\Products\Models\HostingProductComposition;

/** @extends Factory<HostingProductComposition> */
class HostingProductCompositionFactory extends Factory
{
    protected $model = HostingProductComposition::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [];
    }
}
