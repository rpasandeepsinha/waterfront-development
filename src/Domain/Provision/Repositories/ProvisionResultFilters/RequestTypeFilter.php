<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Repositories\ProvisionResultFilters;

use Illuminate\Database\Eloquent\Builder;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\Models\ProvisioningResult;

class RequestTypeFilter
{
    public function __construct(
        private readonly ?ProvisionType $type,
    ) {
    }

    /**
     * @param Builder<ProvisioningResult> $resultQuery
     *
     * @return Builder<ProvisioningResult>
     */
    public function __invoke(Builder $resultQuery, callable $next): Builder
    {
        if ($this->type === null) {
            return $next($resultQuery);
        }

        $resultQuery->whereHas('provisioningRequest', function (Builder $requestQuery) {
            $requestQuery->where('request_type', $this->type);
        });

        return $next($resultQuery);
    }
}
