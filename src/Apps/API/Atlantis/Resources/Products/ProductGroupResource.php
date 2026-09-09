<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Atlantis\Resources\Products;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Domain\Products\Models\ProductGroup;

/**
 * @property-read ProductGroup $resource
 */
class ProductGroupResource extends JsonResource
{
    /**
     * @param Request $request
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'uuid' => $this->resource->uuid,
            'name' => $this->resource->name,
            'slug' => $this->resource->slug,
            'defaultRate' => $this->resource->default_rate,
            'defaultBillingPeriod' => $this->resource->default_billing_period,
            'defaultContractPeriod' => $this->resource->default_contract_period,
        ];
    }
}
