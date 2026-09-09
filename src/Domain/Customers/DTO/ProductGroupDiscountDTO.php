<?php

declare(strict_types=1);

namespace Waterfront\Domain\Customers\DTO;

use Waterfront\Domain\Products\Enums\ProductGroupType;

readonly class ProductGroupDiscountDTO
{
    public function __construct(
        public ProductGroupType $productGroupType,
        public float|int $discountPercentage,
    ) {
    }
}
