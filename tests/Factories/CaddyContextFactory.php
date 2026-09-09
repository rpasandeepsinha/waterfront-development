<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Ramsey\Uuid\Uuid;
use Waterfront\Domain\Provision\Redirects\Models\CaddyContext;

/**
 * @extends Factory<CaddyContext>
 */
class CaddyContextFactory extends Factory
{
    protected $model = CaddyContext::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'context_uuid' => Uuid::uuid4()->toString(),
            'host' => $this->faker->domainName(),
        ];
    }
}
