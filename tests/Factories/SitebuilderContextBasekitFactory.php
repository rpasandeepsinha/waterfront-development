<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Ramsey\Uuid\Uuid;
use Waterfront\Domain\Provision\Sitebuilder\Models\BasekitContext;

/**
 * @extends Factory<BasekitContext>
 */
class SitebuilderContextBasekitFactory extends Factory
{
    protected $model = BasekitContext::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'context_uuid' => Uuid::uuid4()->toString(),
            'user_ref' => $this->faker->numberBetween(1, 2000),
        ];
    }
}
