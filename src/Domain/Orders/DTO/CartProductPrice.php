<?php

declare(strict_types=1);

namespace Waterfront\Domain\Orders\DTO;

use Waterfront\Domain\Orders\Enums\CartProductPriceType;

readonly class CartProductPrice
{
    public function __construct(
        public CartProductPriceType $type,
        public int $period,
        public int $contractPeriodInMonths,
        public int $netPrice,
        public int $grossPrice,
        public ?int $introductionPrice,
        public ?int $introductionPriceRemainingUses,
    ) {
    }
}
