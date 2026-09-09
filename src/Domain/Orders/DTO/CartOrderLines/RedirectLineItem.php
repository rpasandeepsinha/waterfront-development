<?php

declare(strict_types=1);

namespace Waterfront\Domain\Orders\DTO\CartOrderLines;

use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Orders\DTO\CartOrderSubscription;
use Waterfront\Domain\Products\Enums\ProductPriceType;

class RedirectLineItem extends LineItem
{
    public function __construct(
        UuidInterface $uuid,
        string $slug,
        ?string $parentSubscriptionUuid,
        ?string $subscriptionUuid,
        int $billingPeriod,
        int $contractPeriod,
        string $domain,
        ?ProductPriceType $status,
        ?CartOrderSubscription $children,
        ?array $oneTimeServices,
        ?string $experimentSlug,
    ) {
        parent::__construct(
            $uuid,
            $slug,
            $parentSubscriptionUuid,
            $subscriptionUuid,
            $billingPeriod,
            $contractPeriod,
            $domain,
            $status,
            $children,
            $oneTimeServices,
            $experimentSlug,
        );
    }
}
