<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Ramsey\Uuid\Uuid;
use Waterfront\Domain\Provision\Redirects\Models\CaddyRedirectDeployment;

/**
 * @extends Factory<CaddyRedirectDeployment>
 */
class CaddyRedirectDeploymentFactory extends Factory
{
    protected $model = CaddyRedirectDeployment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => Uuid::uuid4()->toString(),
            'caddy_id' => sprintf('redirect:%s', $this->faker->uuid()),
        ];
    }

    public function withRedirectDeployment(): CaddyRedirectDeploymentFactory
    {
        return $this->for(RedirectDeploymentFactory::new());
    }
}
