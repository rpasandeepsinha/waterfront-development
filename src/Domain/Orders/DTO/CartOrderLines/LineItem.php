<?php

declare(strict_types=1);

namespace Waterfront\Domain\Orders\DTO\CartOrderLines;

use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Waterfront\Domain\Orders\DTO\CartOrderSubscription;
use Waterfront\Domain\Products\Enums\ProductPriceType;

abstract class LineItem
{
    /**
     * @param ?OneTimeServiceLineItem[] $oneTimeServices
     */
    public function __construct(
        public readonly UuidInterface $uuid,
        public readonly string $slug,
        public readonly ?string $parentSubscriptionUuid,
        public readonly ?string $subscriptionUuid,
        public readonly int $billingPeriod,
        public readonly int $contractPeriod,
        public readonly ?string $domain,
        public readonly ?ProductPriceType $status,
        public readonly ?CartOrderSubscription $children,
        #[SerializedName('one_time_services')]
        public readonly ?array $oneTimeServices,
        public readonly ?string $experimentSlug,
    ) {
    }
}
