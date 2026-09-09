<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\DTO;

use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Cart\DTO\AppliedPrice;
use Waterfront\Domain\Cart\DTO\RegularPrice;

class ProductWithCalculatedPrice
{
    public function __construct(
        public UuidInterface $uuid,
        public ?UuidInterface $parentItemUuid,
        public string $slug,
        public int $productId,
        public int $billingPeriod,
        public int $contractPeriod,
        public RegularPrice $regularPrice,
        public AppliedPrice $appliedPrice,
        public Price $price,
    ) {
    }
}
