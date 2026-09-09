<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\Provider;

/**
 * @extends Factory<Provider>
 */
class ProviderFactory extends Factory
{
    protected $model = Provider::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'enabled' => $this->faker->boolean(),
            'default' => $this->faker->boolean(),
        ];
    }

    public function emailOnlyDirectAdmin(): self
    {
        return $this->state(fn () => [
            'type' => ProviderType::MAILONLY,
            'enabled' => true,
            'default' => false,
            'slug' => ProviderSlug::DIRECTADMIN,
        ]);
    }

    public function emailOnlyPlesk(): self
    {
        return $this->state(fn () => [
            'type' => ProviderType::MAILONLY,
            'enabled' => true,
            'default' => false,
            'slug' => ProviderSlug::PLESK,
        ]);
    }

    public function siteBuilderBaseKit(): self
    {
        return $this->state(fn () => [
            'type' => ProviderType::SITEBUILDER,
            'enabled' => true,
            'default' => false,
            'slug' => ProviderSlug::BASEKIT,
        ]);
    }

    public function emailOnlyPlaceholder(): self
    {
        return $this->state(fn () => [
            'type' => ProviderType::MAILONLY,
            'enabled' => true,
            'default' => false,
            'slug' => ProviderSlug::PLACEHOLDER,
        ]);
    }

    public function sitebuilderPlaceholder(): self
    {
        return $this->state(fn () => [
            'type' => ProviderType::SITEBUILDER,
            'enabled' => true,
            'default' => false,
            'slug' => ProviderSlug::PLACEHOLDER,
        ]);
    }

    public function domainOpenProvider(): self
    {
        return $this->state(fn (): array => [
            'type' => ProviderType::DOMAIN,
            'enabled' => true,
            'default' => true,
            'slug' => ProviderSlug::OPEN_PROVIDER,
        ]);
    }

    public function domainRtr(): self
    {
        return $this->state(fn (): array => [
            'type' => ProviderType::DOMAIN,
            'enabled' => true,
            'default' => true,
            'slug' => ProviderSlug::REALTIME_REGISTER,
        ]);
    }

    public function domainPlaceholder(): self
    {
        return $this->state(fn (): array => [
            'type' => ProviderType::DOMAIN,
            'enabled' => true,
            'default' => false,
            'slug' => ProviderSlug::PLACEHOLDER,
        ]);
    }

    public function sslRtr(): self
    {
        return $this->state(fn (): array => [
            'type' => ProviderType::SSL,
            'enabled' => true,
            'default' => false,
            'slug' => ProviderSlug::REALTIME_REGISTER,
        ]);
    }

    public function sslOpenProvider(): self
    {
        return $this->state(fn (): array => [
            'type' => ProviderType::SSL,
            'enabled' => true,
            'default' => false,
            'slug' => ProviderSlug::OPEN_PROVIDER,
        ]);
    }

    public function sslPlaceholder(): self
    {
        return $this->state(fn (): array => [
            'type' => ProviderType::SSL,
            'enabled' => true,
            'default' => false,
            'slug' => ProviderSlug::PLACEHOLDER,
        ]);
    }

    public function hostingDirectAdmin(): self
    {
        return $this->state(fn () => [
            'type' => ProviderType::HOSTING,
            'enabled' => true,
            'default' => false,
            'slug' => ProviderSlug::DIRECTADMIN,
        ]);
    }

    public function pleskHosting(): self
    {
        return $this->state([
            'type' => ProviderType::HOSTING,
            'slug' => ProviderSlug::PLESK,
            'enabled' => true,
            'default' => false,
        ]);
    }

    public function hostingPlaceholder(): self
    {
        return $this->state(fn () => [
            'type' => ProviderType::HOSTING,
            'enabled' => true,
            'default' => false,
            'slug' => ProviderSlug::PLACEHOLDER,
        ]);
    }
}
