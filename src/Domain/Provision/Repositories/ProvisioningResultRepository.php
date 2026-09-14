<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Pipeline;
use SortDirection;
use Waterfront\Domain\Provision\DTO\ProvisioningFilteredResult;
use Waterfront\Domain\Provision\DTO\ProvisioningResultQueryFilters;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Models\ProvisioningResult;
use Waterfront\Domain\Provision\Repositories\ProvisionResultFilters\CreateRequestFilter;
use Waterfront\Domain\Provision\Repositories\ProvisionResultFilters\DateFilter;
use Waterfront\Domain\Provision\Repositories\ProvisionResultFilters\LimitFilter;
use Waterfront\Domain\Provision\Repositories\ProvisionResultFilters\RequestTypeFilter;
use Waterfront\Domain\Provision\Repositories\ProvisionResultFilters\RequestUuidFilter;
use Waterfront\Domain\Provision\Repositories\ProvisionResultFilters\RetryFilter;
use Waterfront\Domain\Provision\Repositories\ProvisionResultFilters\StatusFilter;
use Waterfront\Domain\Provision\Repositories\ProvisionResultFilters\TagFilter;
use Waterfront\Domain\Provision\Repositories\ProvisionResultFilters\UuidFilter;

class ProvisioningResultRepository
{
    /**
     *
     * @return Collection<int, ProvisioningFilteredResult>
     */
    public function fetchProvisioningResults(
        ProvisioningResultQueryFilters $queryFilters,
        ?int $limit = null,
    ): Collection {
        $filters = [
            new CreateRequestFilter($queryFilters->onlyCreateRequests),
            new TagFilter($queryFilters->tag),
            new UuidFilter($queryFilters->uuid),
            new RequestUuidFilter($queryFilters->requestUuid),
            new RequestTypeFilter($queryFilters->requestType),
            new StatusFilter($queryFilters->provisionStatus),
            new RetryFilter(
                onlyRetryRequests: $queryFilters->onlyRetryRequests,
                retryOf: $queryFilters->retryOf,
                retryRequester: $queryFilters->retryRequester,
            ),
            new DateFilter(fromDate: $queryFilters->fromDate, toDate: $queryFilters->toDate),
            new LimitFilter($limit),
        ];

        $query = ProvisioningResult::query()->with('provisioningRequest', 'provisioningRequest.retryOf');

        /** @var Builder<ProvisioningResult> $queryWithFilters */
        $queryWithFilters = Pipeline::send($query)->through($filters)->thenReturn();

        /** @var Collection<int, ProvisioningResult> $provisioningResults */
        $provisioningResults = $queryWithFilters->orderBy('created_at', SortDirection::Descending)->get();

        $filteredResults = new Collection();

        foreach ($provisioningResults as $provisioningResult) {
            $provisioningRequest = $provisioningResult->provisioningRequest;

            $filteredResult = new ProvisioningFilteredResult(
                resultId: $provisioningResult->id,
                uuid: $provisioningResult->uuid,
                tag: $provisioningRequest->tag,
                context: $provisioningRequest->context_uuid,
                response: $provisioningResult->response,
                status: $provisioningResult->status,
                createdAt: $provisioningResult->created_at,
                requestCreatedAt: $provisioningRequest->created_at,
                requestUpdatedAt: $provisioningRequest->updated_at,
                requestData: $provisioningRequest->request_data,
                requestUuid: $provisioningRequest->uuid,
                requestName: $provisioningRequest->request_name,
                requestType: $provisioningRequest->request_type,
                provider: $provisioningRequest->provision_provider ?? ProvisionProvider::INTERNAL,
                retryOf: $provisioningRequest->retryOf->uuid ?? null,
                retryRequester: $provisioningRequest->requested_by_uuid,
            );
            $filteredResults->add($filteredResult);
        }

        return $filteredResults;
    }
}
