<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\Deployments;

use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Apps\API\Compass\DTO\ProvisionResultDTO;

/**
 * @property ProvisionResultDTO $resource
 */
class ProvisioningResult extends JsonResource
{
    /**
     * @return mixed[]
     */
    public function toArray($request): array
    {
        return [
            'uuid' => $this->resource->uuid->toString(),
            'response' => $this->resource->response,
            'status' => $this->resource->status->value,
            'created_at' => $this->resource->createdAt,
        ];
    }
}
