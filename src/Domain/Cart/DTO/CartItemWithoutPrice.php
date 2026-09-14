<?php

declare(strict_types=1);

namespace Waterfront\Domain\Cart\DTO;

use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Waterfront\Domain\Products\Enums\ProductPriceType;

readonly class CartItemWithoutPrice
{
    public function __construct(
        #[SerializedName('itemUuid')]
        public UuidInterface $itemUuid,
        #[SerializedName('parentItemUuid')]
        public ?UuidInterface $parentItemUuid,
        #[SerializedName('subscriptionUuid')]
        public ?UuidInterface $subscriptionUuid,
        #[SerializedName('parentSubscriptionUuid')]
        public ?UuidInterface $parentSubscriptionUuid,
        #[SerializedName('productSlug')]
        public string $productSlug,
        #[SerializedName('billingPeriod')]
        public int $billingPeriod,
        #[SerializedName('contractPeriod')]
        public int $contractPeriod,
        #[SerializedName('priceType')]
        public ProductPriceType $priceType,
        #[SerializedName('quantity')]
        public int $quantity,
        #[SerializedName('metaData')]
        public ?MetaData $metaData,
    ) {
    }
}
