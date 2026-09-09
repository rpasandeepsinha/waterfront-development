<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\Domains;

use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Domain\Domains\Models\DomainDeployment;

/** @property DomainDeployment $resource */
class DomainDeploymentResource extends JsonResource
{
    /** @return array<string, int|string|null> */
    public function toArray($request): array
    {
        return [
            'domain_status' => $this->resource->domain_status?->value,
            'registry' => $this->resource->provider->slug->value,
            'business_unit' => $this->resource->businessUnit?->name,
            'business_unit_slug' => $this->resource->businessUnit?->slug,
            'last_result' => $this->resource->last_result,
        ];
    }
}
