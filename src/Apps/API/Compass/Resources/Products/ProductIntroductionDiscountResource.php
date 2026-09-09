<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\Products;

use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Domain\Pricing\Models\ProductIntroductionDiscount;

/** @property ProductIntroductionDiscount $resource */
class ProductIntroductionDiscountResource extends JsonResource
{
    /** @return array<mixed> */
    public function toArray($request): array
    {
        return [
            'contract_period' => $this->resource->contract_period,
            'max_uses_per_customer' => $this->resource->max_uses_per_customer,
            'first_months_discount_period' => $this->resource->first_months_discount_period,
        ];
    }
}
