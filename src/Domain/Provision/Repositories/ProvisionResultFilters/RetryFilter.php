<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Repositories\ProvisionResultFilters;

use Illuminate\Database\Eloquent\Builder;
use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Provision\Models\ProvisioningResult;

class RetryFilter
{
    public function __construct(
        private readonly bool $onlyRetryRequests = false,
        private readonly ?UuidInterface $retryOf = null,
        private readonly ?UuidInterface $retryRequester = null,
    ) {
    }

    /**
     * @param Builder<ProvisioningResult> $resultQuery
     *
     * @return Builder<ProvisioningResult>
     */
    public function __invoke(Builder $resultQuery, callable $next): Builder
    {
        if (! $this->onlyRetryRequests && $this->retryOf === null && $this->retryRequester === null) {
            return $next($resultQuery);
        }

        $resultQuery->whereHas('provisioningRequest', function (Builder $requestQuery): void {
            if ($this->onlyRetryRequests) {
                $requestQuery->whereNotNull('retry_of_request_id')->whereNotNull('requested_by_uuid');
            }

            if ($this->retryRequester !== null) {
                $requestQuery->where('requested_by_uuid', $this->retryRequester);
            }

            if ($this->retryOf !== null) {
                $requestQuery->whereHas('retryOf', function (Builder $retryOfQuery): void {
                    $retryOfQuery->where('uuid', $this->retryOf);
                });
            }
        });

        return $next($resultQuery);
    }
}
