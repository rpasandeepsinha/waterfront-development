<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Repositories\ProvisionResultFilters;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Waterfront\Domain\Provision\Models\ProvisioningResult;

class DateFilter
{
    public function __construct(
        private readonly ?CarbonImmutable $fromDate,
        private readonly ?CarbonImmutable $toDate,
    ) {
    }

    /**
     * @param Builder<ProvisioningResult> $resultQuery
     *
     * @return Builder<ProvisioningResult>
     */
    public function __invoke(Builder $resultQuery, callable $next): Builder
    {
        if ($this->fromDate === null && $this->toDate === null) {
            return $next($resultQuery);
        }

        if ($this->fromDate !== null && $this->toDate !== null) {
            $resultQuery->whereBetween('created_at', [$this->fromDate, $this->toDate]);

            return $next($resultQuery);
        }

        if ($this->fromDate !== null) {
            $resultQuery->where('created_at', '>=', $this->fromDate);
        }

        if ($this->toDate !== null) {
            $resultQuery->where('created_at', '<=', $this->toDate);
        }

        return $next($resultQuery);
    }
}
