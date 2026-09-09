<?php

declare(strict_types=1);

namespace Waterfront\Domain\Cart\DTO;

use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Products\Enums\ProductPriceType;

readonly class CartItemWithPrice
{
    public function __construct(
        public UuidInterface $itemUuid,
        public ?UuidInterface $parentItemUuid,
        public ?UuidInterface $parentSubscriptionUuid,
        public string $productSlug,
        public int $billingPeriod,
        public int $contractPeriod,
        public CartPrice $price,
        public ProductPriceType $priceType,
        public int $quantity,
        public ?MetaData $metaData,
    ) {
    }
}
