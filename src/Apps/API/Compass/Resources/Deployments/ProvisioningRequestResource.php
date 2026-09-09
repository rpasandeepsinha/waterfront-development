<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\Deployments;

use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Domain\Provision\DTO\ProvisioningFilteredResult;

/**
 * @property ProvisioningFilteredResult $resource
 */
class ProvisioningRequestResource extends JsonResource
{
    /**
     * @return mixed[]
     */
    public function toArray($request): array
    {
        return [
            'uuid' => $this->resource->uuid->toString(),
            'subscription_uuid' => $this->resource->tag->toString(),
            'response' => $this->resource->response,
            'status' => $this->resource->status->value,
            'created_at' => $this->resource->createdAt?->toISOString(),
            'requested_data' => $this->resource->requestData,
            'requested_at' => $this->resource->requestCreatedAt?->toISOString(),
            'request_name' => $this->resource->requestName,
            'request_type' => $this->resource->requestType->value,
        ];
    }
}
