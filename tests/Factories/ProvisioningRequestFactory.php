<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Ramsey\Uuid\Uuid;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\Models\ProvisioningRequest;

/**
 * @extends Factory<ProvisioningRequest>
 */
class ProvisioningRequestFactory extends Factory
{
    protected $model = ProvisioningRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => Uuid::uuid4(),
            'tag' => Uuid::uuid4(),
            'request_data' => '{}',
            'request_type' => $this->faker->randomElement(ProvisionType::class),
            'request_name' => $this->faker->randomElement(ProvisionRequestName::class),
            'context_uuid' => $this->faker->uuid(),
            'provision_provider' => ProvisionProvider::cases()[array_rand(ProvisionProvider::cases())],
        ];
    }

    public function hosting(): ProvisioningRequestFactory
    {
        return $this->state([
            'request_type' => ProvisionType::HOSTING,
            'provision_provider' => ProvisionProvider::PLESK,
        ]);
    }

    public function extension(): ProvisioningRequestFactory
    {
        return $this->state([
            'request_type' => ProvisionType::DOMAIN_NAME,
            'provision_provider' => ProvisionProvider::RTR,
        ]);
    }

    public function isRetry(?int $id = null): ProvisioningRequestFactory
    {
        return $this->state([
            'retry_of_request_id' => $id ?? ProvisioningRequestFactory::new()->createOne()->id,
            'requested_by_uuid' => Uuid::uuid4(),
        ]);
    }

    public function redirect(): ProvisioningRequestFactory
    {
        return $this->state([
            'request_type' => ProvisionType::REDIRECT,
            'provision_provider' => ProvisionProvider::INTERNAL,
        ]);
    }

    public function vps(): ProvisioningRequestFactory
    {
        return $this->state([
            'request_type' => ProvisionType::VPS,
            'provision_provider' => ProvisionProvider::CLOUDSTACK,
        ]);
    }

    public function dns(): ProvisioningRequestFactory
    {
        return $this->state([
            'request_type' => ProvisionType::DNS,
            'provision_provider' => ProvisionProvider::POWERDNS,
        ]);
    }

    public function m365(): ProvisioningRequestFactory
    {
        return $this->state([
            'request_type' => ProvisionType::M365,
            'provision_provider' => ProvisionProvider::MICROSOFT_ONLINE,
        ]);
    }

    public function ssl(): ProvisioningRequestFactory
    {
        return $this->state([
            'request_type' => ProvisionType::SSL,
            'provision_provider' => ProvisionProvider::RTR,
        ]);
    }

    public function sitebuilder(): ProvisioningRequestFactory
    {
        return $this->state([
            'request_type' => ProvisionType::SITEBUILDER,
            'provision_provider' => ProvisionProvider::BASEKIT,
        ]);
    }

    public function backup(): ProvisioningRequestFactory
    {
        return $this->state([
            'request_type' => ProvisionType::BACKUP,
            'provision_provider' => ProvisionProvider::ACRONIS,
        ]);
    }
}
