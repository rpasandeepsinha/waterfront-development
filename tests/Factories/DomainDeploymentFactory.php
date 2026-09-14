<?php

declare(strict_types=1);

namespace Tests\Factories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Ramsey\Uuid\Uuid;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Infra\RtrClient\Services\Enums\DomainStatus as RtrDomainStatus;

/**
 * @extends Factory<DomainDeployment>
 */
class DomainDeploymentFactory extends Factory
{
    protected $model = DomainDeployment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subscription_uuid' => Uuid::uuid4(),
            'domain_status' => null,
            'last_result' => json_encode([]),
            'last_result_received' => CarbonImmutable::now(),
            'dnssec_enabled' => false,
            'private_whois_enabled' => false,
        ];
    }

    /** @param array<string, mixed> $attributes */
    public function withSubscription(Product $product, array $attributes = []): self
    {
        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->for($product)
            ->createOne($attributes);

        return $this->state(fn (): array => [
            'subscription_uuid' => $subscription->uuid,
        ]);
    }

    public function withRtrProvider(): self
    {
        $rtrProvider = Provider::where('slug', ProviderSlug::REALTIME_REGISTER)
            ->where('type', ProviderType::DOMAIN)
            ->first();

        return $this->state(fn (): array => [
            'provider_id' => $rtrProvider->id ?? ProviderFactory::new()->createOne([
                'type' => ProviderType::DOMAIN,
                'enabled' => true,
                'default' => true,
                'slug' => ProviderSlug::REALTIME_REGISTER,
            ]),
        ]);
    }

    public function withOpenProvider(): self
    {
        $openProvider = Provider::where('slug', ProviderSlug::OPEN_PROVIDER)
            ->where('type', ProviderType::DOMAIN)
            ->first();

        return $this->state(fn (): array => [
            'provider_id' => $openProvider->id ?? ProviderFactory::new()->createOne([
                'type' => ProviderType::DOMAIN,
                'enabled' => true,
                'default' => true,
                'slug' => ProviderSlug::OPEN_PROVIDER,
            ]),
        ]);
    }

    public function withPlaceholderProvider(): self
    {
        $placeholderProvider = Provider::where('slug', ProviderSlug::PLACEHOLDER)
            ->where('type', ProviderType::DOMAIN)
            ->first();

        return $this->state(fn (): array => [
            'provider_id' => $placeholderProvider->id ?? ProviderFactory::new()->createOne([
                'type' => ProviderType::DOMAIN,
                'enabled' => true,
                'default' => false,
                'slug' => ProviderSlug::PLACEHOLDER,
            ]),
        ]);
    }

    public function withRtrDomainStatus(RtrDomainStatus $domainStatus): self
    {
        return $this->state(fn (): array => [
            'domain_status' => $domainStatus,
        ]);
    }
}
