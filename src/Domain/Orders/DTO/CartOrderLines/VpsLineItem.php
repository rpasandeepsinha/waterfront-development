<?php

declare(strict_types=1);

namespace Waterfront\Domain\Orders\DTO\CartOrderLines;

use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Orders\DTO\CartOrderSubscription;
use Waterfront\Domain\Products\Enums\ProductPriceType;

class VpsLineItem extends LineItem
{
    /**
     * @param ?OneTimeServiceLineItem[] $oneTimeServices
     */
    public function __construct(
        UuidInterface $uuid,
        string $slug,
        ?string $parentSubscriptionUuid,
        ?string $subscriptionUuid,
        int $billingPeriod,
        int $contractPeriod,
        ?ProductPriceType $status,
        ?CartOrderSubscription $children,
        ?array $oneTimeServices,
    ) {
        parent::__construct(
            $uuid,
            $slug,
            $parentSubscriptionUuid,
            $subscriptionUuid,
            $billingPeriod,
            $contractPeriod,
            null,
            $status,
            $children,
            $oneTimeServices,
        );
    }
}
