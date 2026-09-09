<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Repositories\ProvisionResultFilters;

use Illuminate\Database\Eloquent\Builder;
use Waterfront\Domain\Provision\Models\ProvisioningResult;

class LimitFilter
{
    public function __construct(
        private readonly ?int $limit
    ) {
    }

    /**
     * @param Builder<ProvisioningResult> $resultQuery
     *
     * @return Builder<ProvisioningResult>
     */
    public function __invoke(Builder $resultQuery, callable $next): Builder
    {
        if ($this->limit === null) {
            return $next($resultQuery);
        }

        $resultQuery->limit($this->limit);

        return $next($resultQuery);
    }
}
