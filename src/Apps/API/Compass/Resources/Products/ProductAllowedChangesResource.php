<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\Products;

use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Domain\Subscriptions\Models\ProductAllowedChange;

/** @property ProductAllowedChange $resource */
class ProductAllowedChangesResource extends JsonResource
{
    /** @return array<mixed> */
    public function toArray($request): array
    {
        return [
            'id' => $this->resource->id,
            'to_product' => $this->resource->toProduct === null
                ? null
                : [
                    'id' => $this->resource->toProduct->id,
                    'uuid' => $this->resource->toProduct->uuid,
                    'slug' => $this->resource->toProduct->slug,
                    'name' => $this->resource->toProduct->name,
                ],
            'change_type' => $this->resource->change_type,
            'display_order' => $this->resource->display_order,
            'is_available_for_customer' => $this->resource->is_available_for_customer,
            'is_deleted' => is_null($this->resource->deleted_at) ? false : true,
        ];
    }
}
