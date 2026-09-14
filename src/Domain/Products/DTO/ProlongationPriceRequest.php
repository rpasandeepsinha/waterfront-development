<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\DTO;

use Waterfront\Domain\Products\Models\Product;

class ProlongationPriceRequest extends ProductPriceRequest
{
    public function __construct(
        Product $product,
        int $quantity = 1,
        ?int $contractPeriod = null,
        ?int $billingPeriod = null,
        public ?string $experimentSlug = null,
    ) {
        parent::__construct($product, $quantity, $contractPeriod, $billingPeriod);
    }
}
