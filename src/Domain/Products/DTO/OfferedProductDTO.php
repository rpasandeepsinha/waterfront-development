<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\DTO;

use Waterfront\Domain\Products\Models\Product;

readonly class OfferedProductDTO
{
    public function __construct(
        public Product $product,
        public bool $isFree,
    ) {
    }
}
