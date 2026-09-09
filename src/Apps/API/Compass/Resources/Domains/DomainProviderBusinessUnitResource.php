<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\Domains;

use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Domain\Domains\Models\DomainProviderBusinessUnit;

/** @property DomainProviderBusinessUnit $resource */
class DomainProviderBusinessUnitResource extends JsonResource
{
    /** @return array<string, string> */
    public function toArray($request): array
    {
        return [
            'slug' => $this->resource->slug,
            'name' => $this->resource->name,
        ];
    }
}
