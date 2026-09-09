<?php

declare(strict_types=1);

namespace Waterfront\Domain\Cart\DTO;

readonly class CartPrice
{
    public function __construct(
        public RegularPrice $regularPrice,
        public AppliedPrice $appliedPrice,
    ) {
    }
}
