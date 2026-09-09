<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\Products;

use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Domain\Products\Models\ProductAddonCoupling;

/** @property ProductAddonCoupling $resource */
class ProductAddonResource extends JsonResource
{
    /** @return array<mixed> */
    public function toArray($request): array
    {
        return [
            'id' => $this->resource->id,
            'addon_product' => [
                'id' => $this->resource->addonProduct->id,
                'uuid' => $this->resource->addonProduct->uuid,
                'slug' => $this->resource->addonProduct->slug,
                'name' => $this->resource->addonProduct->name,
            ],
        ];
    }
}
