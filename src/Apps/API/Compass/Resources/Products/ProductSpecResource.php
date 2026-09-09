<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\Products;

use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Domain\Products\Models\ProductSpec;

/** @property ProductSpec $resource */
class ProductSpecResource extends JsonResource
{
    /** @return array<mixed> */
    public function toArray($request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'value' => $this->resource->value,
        ];
    }
}
