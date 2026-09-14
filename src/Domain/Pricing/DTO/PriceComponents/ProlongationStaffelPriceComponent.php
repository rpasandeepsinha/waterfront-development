<?php

declare(strict_types=1);

namespace Waterfront\Domain\Pricing\DTO\PriceComponents;

use Waterfront\Domain\Pricing\Enums\PriceComponentType;

class ProlongationStaffelPriceComponent extends PriceComponent
{
    /**
     * @param non-negative-int|null $fixedDiscount
     * @param non-negative-int|null $fixedPrice
     * @param non-negative-int      $newPrice
     * @param positive-int|null     $appliedOrder
     */
    public function __construct(
        public ?int $fixedDiscount,
        public ?float $percentageDiscount,
        public ?int $fixedPrice,
        public int $newPrice,
        public ?int $appliedOrder = null,
    ) {
        assert($percentageDiscount === null || $percentageDiscount >= 0.0 && $percentageDiscount <= 100.0);
        assert($fixedDiscount !== null || $percentageDiscount !== null || $fixedPrice !== null);

        parent::__construct(
            PriceComponentType::PROLONGATION_STAFFEL,
            $fixedDiscount,
            $percentageDiscount,
            $fixedPrice,
            $newPrice,
            $appliedOrder,
        );
    }
}
