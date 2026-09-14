<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Repositories\ProvisionResultFilters;

use Illuminate\Database\Eloquent\Builder;
use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Provision\Models\ProvisioningResult;

class UuidFilter
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

        $resultQuery->where('uuid', $this->uuid);

        return $next($resultQuery);
    }
}
