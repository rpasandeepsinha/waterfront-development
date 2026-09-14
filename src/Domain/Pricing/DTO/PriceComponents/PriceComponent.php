<?php

declare(strict_types=1);

namespace Waterfront\Domain\Pricing\DTO\PriceComponents;

use Waterfront\Domain\Pricing\Enums\PriceComponentType;

abstract class PriceComponent
{
    /**
     * @param non-negative-int|null $fixedDiscount
     * @param non-negative-int|null $fixedPrice
     * @param non-negative-int      $newPrice
     * @param positive-int|null     $appliedOrder
     */
    public function __construct(
        public PriceComponentType $type,
        public ?int $fixedDiscount,
        public ?float $percentageDiscount,
        public ?int $fixedPrice,
        public int $newPrice,
        public ?int $appliedOrder,
    ) {
        assert($percentageDiscount === null || $percentageDiscount >= 0.0 && $percentageDiscount <= 100.0);
    }
}
