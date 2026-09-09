<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\Products;

use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Domain\Products\Models\ProductGroup;

/** @property ProductGroup $resource */
class ProductGroupResource extends JsonResource
{
    /** @return array<mixed> */
    public function toArray($request): array
    {
        return [
            'uuid' => $this->resource->uuid,
            'slug' => $this->resource->slug,
            'name' => $this->resource->name,
            'contractPeriod' => $this->resource->default_contract_period,
            'billingPeriod' => $this->resource->default_billing_period,
        ];
    }
}
