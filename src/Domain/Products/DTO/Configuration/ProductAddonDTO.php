<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\DTO\Configuration;

readonly class ProductAddonDTO
{
    public function __construct(
        public int $productId,
    ) {
    }
}
