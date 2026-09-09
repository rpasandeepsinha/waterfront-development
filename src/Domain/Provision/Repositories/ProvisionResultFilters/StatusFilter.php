<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Repositories\ProvisionResultFilters;

use Illuminate\Database\Eloquent\Builder;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Models\ProvisioningResult;

class StatusFilter
{
    /**
     * @param list<ProvisionStatus>|null $statuses
     */
    public function __construct(private readonly ?array $statuses)
    {
    }

    /**
     * @param Builder<ProvisioningResult> $resultQuery
     *
     * @return Builder<ProvisioningResult>
     */
    public function __invoke(Builder $resultQuery, callable $next): Builder
    {
        if ($this->statuses === null || $this->statuses === []) {
            return $next($resultQuery);
        }

        $resultQuery->whereIn('status', $this->statuses);

        return $next($resultQuery);
    }
}
