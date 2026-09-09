<?php

declare(strict_types=1);

namespace Waterfront\Domain\Pricing\DTO\PriceComponents;

use Waterfront\Domain\Pricing\Enums\PriceComponentType;

class CustomIndefinitePriceComponent extends PriceComponent
{
    /**
     * @param non-negative-int $price
     */
    public function __construct(
        public int $price,
    ) {
        parent::__construct(PriceComponentType::CUSTOM_INDEFINITE, null, null, $price, $price, 1);
    }
}
