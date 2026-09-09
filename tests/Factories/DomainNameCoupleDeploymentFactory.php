<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Ramsey\Uuid\Uuid;
use Waterfront\Domain\Provision\DomainNames\Coupling\Models\DomainNameCoupleDeployment;
use Waterfront\Domain\Provision\Enums\ProvisionType;

/**
 * @extends Factory<DomainNameCoupleDeployment>
 */
class DomainNameCoupleDeploymentFactory extends Factory
{
    protected $model = DomainNameCoupleDeployment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => Uuid::uuid4()->toString(),
            'origin_provisioning_request_id' => ProvisioningRequestFactory::new()->extension(),
            'domain' => $this->faker->domainName(),
            'couple_type' => $this->faker->randomElement(ProvisionType::cases()),
            'deployment_uuid' => Uuid::uuid4()->toString(),
        ];
    }

    public function hostingCoupling(): self
    {
        return $this->state([
            'origin_provisioning_request_id' => ProvisioningRequestFactory::new()->hosting(),
            'couple_type' => ProvisionType::HOSTING,
        ]);
    }

    public function redirectCoupling(): self
    {
        return $this->state([
            'origin_provisioning_request_id' => ProvisioningRequestFactory::new()->redirect(),
            'couple_type' => ProvisionType::REDIRECT,
        ]);
    }

    public function sslCoupling(): self
    {
        return $this->state([
            'origin_provisioning_request_id' => ProvisioningRequestFactory::new()->ssl(),
            'couple_type' => ProvisionType::SSL,
        ]);
    }

    public function dnsCoupling(): self
    {
        return $this->state([
            'origin_provisioning_request_id' => ProvisioningRequestFactory::new()->dns(),
            'couple_type' => ProvisionType::DNS,
        ]);
    }

    public function vpsCoupling(): self
    {
        return $this->state([
            'origin_provisioning_request_id' => ProvisioningRequestFactory::new()->vps(),
            'couple_type' => ProvisionType::VPS,
        ]);
    }
}
