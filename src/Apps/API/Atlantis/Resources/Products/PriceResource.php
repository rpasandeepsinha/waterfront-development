<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Atlantis\Resources\Products;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Domain\Pricing\DTO\PriceComponents\IntroductionPriceComponent;
use Waterfront\Domain\Pricing\DTO\PriceComponents\PriceComponent;
use Waterfront\Domain\Pricing\DTO\PriceComponents\PromotionPriceComponent;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Products\DTO\Price;

/**
 * @property-read Price $resource
 */
class PriceResource extends JsonResource
{
    /**
     * @param Request $request
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $actionPeriod = $this->resource->actionPeriod;
        $actionPeriodPrice = $this->resource->actionPeriodPrice;

        $introductionPriceComponent = array_find(
            $this->resource->possiblePriceComponents,
            fn (PriceComponent $priceComponent): bool => $priceComponent instanceof IntroductionPriceComponent
        );

        if ($introductionPriceComponent !== null) {
            $actionPeriod = $introductionPriceComponent->firstMonthsDiscountPeriod;
            $actionPeriodPrice = $introductionPriceComponent->fixedPrice;
        }

        $data = [
            'type' => $this->resource->type->value,
            'period' => $this->resource->billingPeriod,
            'contract_period_in_months' => $this->resource->contractPeriod,
            'orderable' => $this->resource->orderable,
            'net_price' => $this->resource->calculatedPrice,
            'gross_price' => $this->resource->regularPrice,
            'regular_price' => $this->resource->regularPrice,
            'price_explanation' => $this->resource->priceExplanation?->getTranslations(),
            'introduction_price' => $introductionPriceComponent?->fixedPrice,
            'introduction_price_remaining_uses' => $introductionPriceComponent->remainingUses ?? 0,
            'action_period' => $actionPeriod,
            'action_period_price' => $actionPeriodPrice,
        ];

        $promotion = array_find(
            $this->resource->possiblePriceComponents,
            fn (PriceComponent $priceComponent): bool => $priceComponent->type === PriceComponentType::PROMOTION
        );

        if ($promotion instanceof PromotionPriceComponent) {
            $data['promotion_price'] = $promotion->newPrice;
        }

        if ($this->resource->discountPrice !== null) {
            $data['discount_price'] = $this->resource->discountPrice;
        }

        return $data;
    }
}
