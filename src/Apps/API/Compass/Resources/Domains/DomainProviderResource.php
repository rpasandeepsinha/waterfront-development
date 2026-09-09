<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\Domains;

use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Domain\Providers\Models\Provider;

/** @property Provider $resource */
class DomainProviderResource extends JsonResource
{
    /** @return array<string, string|bool|null> */
    public function toArray($request): array
    {
        return [
            'slug' => $this->resource->slug->value,
            'type' => $this->resource->type->value,
            'enabled' => $this->resource->enabled,
            'default' => $this->resource->default,
        ];
    }
}
