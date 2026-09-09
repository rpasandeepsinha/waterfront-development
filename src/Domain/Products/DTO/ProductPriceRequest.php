<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\DTO;

use Waterfront\Domain\Products\Models\Product;

abstract class ProductPriceRequest
{
    public function __construct(
        public Product $product,
        public int $quantity = 1,
        public ?int $contractPeriod = null,
        public ?int $billingPeriod = null,
        public ?string $experimentSlug = null,
    ) {
    }
}
