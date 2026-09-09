<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\DTO;

use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Products\Enums\ProductPriceType;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class ProductWithPeriodsAndPrice
{
    public function __construct(
        public readonly UuidInterface $uuid,
        public readonly ?UuidInterface $parentItemUuid,
        public readonly string $slug,
        public readonly int $productId,
        public readonly int $billingPeriod,
        public readonly int $contractPeriod,
        public ProductPriceType $priceType,
        public ?Price $price,
        public ?Subscription $parentSubscription,
        public ?Subscription $subscription,
        public ?string $experimentSlug,
    ) {
    }
}
