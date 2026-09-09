<?php

declare(strict_types=1);

namespace Waterfront\Domain\Cart\DTO;

readonly class RegularPrice
{
    public function __construct(
        public int $priceInclVat,
        public int $priceExclVat,
    ) {
    }
}
