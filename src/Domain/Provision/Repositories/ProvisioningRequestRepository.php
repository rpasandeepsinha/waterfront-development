<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\Models\ProvisioningRequest;

class ProvisioningRequestRepository
{
    public function findByUuid(UuidInterface $uuid): ?ProvisioningRequest
    {
        return ProvisioningRequest::query()->where('uuid', $uuid)->first();
    }

    public function findById(int $id): ?ProvisioningRequest
    {
        return ProvisioningRequest::find($id);
    }

    /** @return Collection<int, ProvisioningRequest> */
    public function findByTag(UuidInterface $uuid): Collection
    {
        return ProvisioningRequest::query()->where('tag', $uuid)->get();
    }

    public function createRequestExists(UuidInterface $tag, ProvisionType $requestType): bool
    {
        return $this->getCreateRequestQuery($tag, $requestType)->exists();
    }

    public function createRequestCount(UuidInterface $tag, ProvisionType $requestType): int
    {
        return $this->getCreateRequestQuery($tag, $requestType)->count();
    }

    /**
     * @return Builder<ProvisioningRequest>
     */
    private function getCreateRequestQuery(UuidInterface $tag, ProvisionType $requestType): Builder
    {
        return ProvisioningRequest::query()
            ->where('tag', $tag)
            ->where('request_type', $requestType)
            ->whereIn('request_name', ProvisionRequestName::getCreateRequests())
            ->whereHas('result', function (Builder $query) {
                $query->where('status', ProvisionStatus::SUCCESS);
            });
    }
}
