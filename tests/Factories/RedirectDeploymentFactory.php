<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Ramsey\Uuid\Uuid;
use Waterfront\Domain\Provision\Redirects\Enums\RedirectType;
use Waterfront\Domain\Provision\Redirects\Models\RedirectDeployment;

/**
 * @extends Factory<RedirectDeployment>
 */
class RedirectDeploymentFactory extends Factory
{
    protected $model = RedirectDeployment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => Uuid::uuid4()->toString(),
            'origin_provisioning_request_id' => ProvisioningRequestFactory::new()->redirect(),
            'source' => $this->faker->domainName(),
            'destination' => $this->faker->domainName(),
            'type' => RedirectType::PERMANENT,
            'context_uuid' => Uuid::uuid4()->toString(),
        ];
    }

    public function withPermanent(): self
    {
        return $this->state([
            'type' => RedirectType::PERMANENT,
        ]);
    }

    public function withTemporary(): self
    {
        return $this->state([
            'type' => RedirectType::TEMPORARY,
        ]);
    }

    public function withFrame(): self
    {
        return $this->state([
            'type' => RedirectType::FRAME,
        ]);
    }
}
