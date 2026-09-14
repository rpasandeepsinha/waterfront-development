<?php

declare(strict_types=1);

namespace Waterfront\Domain\Domains\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Waterfront\Domain\Domains\Models\DomainProviderBusinessUnit;
use Waterfront\Domain\Domains\Models\OpenproviderProviderCredentials;
use Waterfront\Domain\Domains\Models\RtrProviderCredentials;

class DomainProviderBusinessUnitRepository
{
    /**
     * @return Collection<int, DomainProviderBusinessUnit>
     */
    public function getAll(): Collection
    {
        return DomainProviderBusinessUnit::query()->orderBy('name')->get();
    }

    public function findById(int $id): ?DomainProviderBusinessUnit
    {
        return DomainProviderBusinessUnit::query()->find($id);
    }

    /**
     * @throws ModelNotFoundException
     */
    public function findBySlug(string $slug): DomainProviderBusinessUnit
    {
        return DomainProviderBusinessUnit::query()->where('slug', $slug)->firstOrFail();
    }

    public function hasOpenProviderCredentials(int $businessUnitId): bool
    {
        return OpenproviderProviderCredentials::query()->where('domain_business_unit_id', $businessUnitId)->exists();
    }

    public function hasRealtimeRegisterCredentials(int $businessUnitId): bool
    {
        return RtrProviderCredentials::query()->where('domain_business_unit_id', $businessUnitId)->exists();
    }
}
