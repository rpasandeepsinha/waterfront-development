<?php

declare(strict_types=1);

namespace Waterfront\Domain\Pricing\DTO\PriceComponents;

use Waterfront\Domain\Pricing\Enums\PriceComponentType;

class ExperimentPriceLadderPriceComponent extends PriceComponent
{
    /**
     * @param non-negative-int  $price
     * @param positive-int|null $appliedOrder
     */
    public function __construct(
        public int $price,
        public ?int $appliedOrder = null,
    ) {
        parent::__construct(PriceComponentType::EXPERIMENT_PRICE_LADDER, null, null, $price, $price, $appliedOrder);
    }
}
