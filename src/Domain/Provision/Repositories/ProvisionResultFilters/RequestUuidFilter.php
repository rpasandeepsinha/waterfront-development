<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Repositories\ProvisionResultFilters;

use Illuminate\Database\Eloquent\Builder;
use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Provision\Models\ProvisioningResult;

class RequestUuidFilter
{
    public function __construct(
        private readonly ?UuidInterface $uuid,
    ) {
    }

    /**
     * @param Builder<ProvisioningResult> $resultQuery
     *
     * @return Builder<ProvisioningResult>
     */
    public function __invoke(Builder $resultQuery, callable $next): Builder
    {
        if ($this->uuid === null) {
            return $next($resultQuery);
        }

        $resultQuery->whereHas('provisioningRequest', function (Builder $requestQuery) {
            $requestQuery->where('uuid', $this->uuid);
        });

        return $next($resultQuery);
    }
}
