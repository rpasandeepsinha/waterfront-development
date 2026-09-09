<?php

declare(strict_types=1);

namespace Waterfront\Domain\Providers;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Waterfront\Domain\Providers\Enums\ProviderSettingKey;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Providers\Models\ProviderSetting;

class ProviderRepository
{
    public function findById(int $id): ?Provider
    {
        return Provider::where('id', $id)->first();
    }

    /**
     * @throws ModelNotFoundException<Provider>
     */
    public function getByType(ProviderType $type, ProviderSlug $slug): Provider
    {
        return Provider::where('type', $type)->where('slug', $slug)->firstOrFail();
    }

    /**
     * @throws ModelNotFoundException<Provider>
     */
    public function getEnabledByType(ProviderType $type, ProviderSlug $slug): Provider
    {
        return Provider::where('type', $type)->where('slug', $slug)->where('enabled', true)->firstOrFail();
    }

    /**
     * @return Collection<int, Provider>
     */
    public function getAllByType(ProviderType $type): Collection
    {
        return Provider::where('type', $type)->get();
    }

    /**
     * @throws ModelNotFoundException<Provider>
     */
    public function getEnabledDefaultByType(ProviderType $type): Provider
    {
        return Provider::where('type', $type)->where('default', true)->where('enabled', true)->firstOrFail();
    }

    /**
     * @throws ModelNotFoundException<Provider>
     */
    public function getSettingByKey(Provider $provider, ProviderSettingKey $key): ProviderSetting
    {
        return $provider->settings()->where('key', $key)->firstOrFail();
    }

    public function getBySlug(ProviderSlug $slug): Provider
    {
        return Provider::where('slug', $slug)->firstOrFail();
    }
}
