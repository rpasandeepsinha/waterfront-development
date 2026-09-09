<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Ramsey\Uuid\Uuid;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;

/**
 * @extends Factory<HostingDeployment>
 */
class HostingDeploymentFactory extends Factory
{
    protected $model = HostingDeployment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plesk_customer_username' => $this->faker->name(),
            'directadmin_customer_username' => $this->faker->text(10),
            'plesk_customer_id' => $this->faker->randomNumber(),
            'server_id'         => (new ServerFactory()),
            'subscription_uuid' => Uuid::uuid4(),
        ];
    }

    public function withPleskProvider(): HostingDeploymentFactory
    {
        return $this->state(fn () => [
            'provider_id' => new ProviderFactory()->createOne(['type' => ProviderType::HOSTING, 'slug' => ProviderSlug::PLESK, 'enabled' => true, 'default' => true])->id,
            'server_id' => new ServerFactory()->plesk()->createOne()->id,
            'directadmin_customer_username' => null,
        ]);
    }

    public function withMailOnlyProvider(): HostingDeploymentFactory
    {
        return $this->state(fn () => [
            'provider_id' => null,
            'server_id' => null,
            'mail_only_provider_id' => new ProviderFactory()->createOne(['type' => ProviderType::MAILONLY, 'slug' => ProviderSlug::DIRECTADMIN, 'enabled' => true, 'default' => true])->id,
            'mail_only_server_id' => new ServerFactory()->directadminMail()->createOne()->id,
            'plesk_customer_id' => null,
            'plesk_customer_username' => null,
        ]);
    }

    public function withDirectAdminProvider(): HostingDeploymentFactory
    {
        return $this->state(fn () => [
            'provider_id' => new ProviderFactory()->createOne(['type' => ProviderType::HOSTING, 'slug' => ProviderSlug::DIRECTADMIN, 'enabled' => true, 'default' => true])->id,
            'server_id' => new ServerFactory()->directadmin()->createOne()->id,
            'plesk_customer_id' => null,
            'plesk_customer_username' => null,
        ]);
    }
}
