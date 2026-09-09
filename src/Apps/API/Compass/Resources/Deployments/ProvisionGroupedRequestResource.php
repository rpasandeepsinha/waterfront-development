<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\Deployments;

use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Apps\API\Compass\DTO\ProvisioningRequestDTO;

/**
 * @property ProvisioningRequestDTO $resource
 */
class ProvisionGroupedRequestResource extends JsonResource
{
    /**
     * @return mixed[]
     */
    public function toArray($request): array
    {
        return [
            'uuid' => $this->resource->uuid->toString(),
            'tag' => $this->resource->tag->toString(),
            'context' => $this->resource->context?->toString(),
            'created_at' => $this->resource->createdAt,
            'updated_at' => $this->resource->updatedAt,
            'requested_data' => $this->resource->requestData,
            'request_name' => $this->resource->requestName,
            'request_type' => $this->resource->requestType,
            'provision_provider' => $this->resource->provisionProvider,
            'provisioning_results' => ProvisioningResult::collection($this->resource->provisioningResults),
        ];
    }
}
