<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Repositories\ProvisionResultFilters;

use Illuminate\Database\Eloquent\Builder;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;
use Waterfront\Domain\Provision\Models\ProvisioningResult;

class CreateRequestFilter
{
    public function __construct(private readonly bool $onlyCreateRequests)
    {
    }

    /**
     * @param Builder<ProvisioningResult> $resultQuery
     *
     * @return Builder<ProvisioningResult>
     */
    public function __invoke(Builder $resultQuery, callable $next): Builder
    {
        if (! $this->onlyCreateRequests) {
            return $next($resultQuery);
        }

        $resultQuery->whereHas('provisioningRequest', function (Builder $requestQuery) {
            $requestQuery->whereIn('request_name', ProvisionRequestName::getCreateRequests());
        });

        return $next($resultQuery);
    }
}
