<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Transformers;

use Illuminate\Support\Collection;
use Waterfront\Apps\API\Compass\DTO\ProvisioningRequestDTO;
use Waterfront\Apps\API\Compass\DTO\ProvisionResultDTO;
use Waterfront\Domain\Provision\DTO\ProvisioningFilteredResult;

class ProvisioningResultGroupingTransformer
{
    /**
     * @param Collection<int, ProvisioningFilteredResult> $results
     *
     * @return Collection<int, ProvisioningRequestDTO>
     */
    public function transform(Collection $results): Collection
    {
        $groupedByRequest = $results->groupBy(fn ($result) => $result->requestUuid->toString());

        return $groupedByRequest->map(function (Collection $results) {
            $firstResult = $results->first();
            assert($firstResult instanceof ProvisioningFilteredResult);

            $provisionResults = $results->map(fn (ProvisioningFilteredResult $result) => new ProvisionResultDTO(
                uuid: $result->uuid,
                createdAt: $result->createdAt,
                response: $result->response,
                status: $result->status,
            ))->all();

            return new ProvisioningRequestDTO(
                uuid: $firstResult->requestUuid,
                tag: $firstResult->tag,
                requestData: $firstResult->requestData,
                requestType: $firstResult->requestType,
                createdAt: $firstResult->requestCreatedAt ?? null,
                updatedAt: $firstResult->requestUpdatedAt ?? null,
                requestName: $firstResult->requestName,
                context: $firstResult->context ?? null,
                provisionProvider: $firstResult->provider,
                provisioningResults: $provisionResults,
                retryOf: $firstResult->retryOf ?? null,
                retryRequester: $firstResult->retryRequester ?? null,
            );
        })->values();
    }
}
