<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\Products;

use Waterfront\Domain\Pricing\Models\ProductPriceComponent;

class PriceComponentPresenter
{
    /** @return array<mixed> */
    public function toArray(ProductPriceComponent $price): array
    {
        return [
            'id' => $price->id,
            'billingPeriod' => $price->billing_period,
            'contractPeriod' => $price->contract_period,
            'price' => $price->price,
            'orderable' => $price->orderable,
            'type' => $price->type,
            'startsAt' => $price->starts_at,
            'expiresAt' => $price->expires_at,
            'priceExplanation' => $price->priceExplanation,
        ];
    }
}
