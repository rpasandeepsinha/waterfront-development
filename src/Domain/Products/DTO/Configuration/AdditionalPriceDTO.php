<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\DTO\Configuration;

use Waterfront\Domain\Pricing\Enums\PriceComponentType;

readonly class AdditionalPriceDTO
{
    public function __construct(
        public PriceComponentType $type,
        public int $price,
    ) {
    }
}
