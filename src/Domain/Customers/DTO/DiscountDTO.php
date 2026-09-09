<?php

declare(strict_types=1);

namespace Waterfront\Domain\Customers\DTO;

use Waterfront\Domain\Products\DTO\Price;

readonly class DiscountDTO
{
    public function __construct(
        public int $price,
        public int $contractPeriod,
        public int $billingPeriod,
        public Price $baseProductProlongationPrice
    ) {
    }
}
