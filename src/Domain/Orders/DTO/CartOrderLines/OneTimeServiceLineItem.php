<?php

declare(strict_types=1);

namespace Waterfront\Domain\Orders\DTO\CartOrderLines;

use Ramsey\Uuid\UuidInterface;

class OneTimeServiceLineItem extends LineItem
{
    public function __construct(
        UuidInterface $uuid,
        string $slug,
        ?UuidInterface $parentItemUuid,
    ) {
        parent::__construct(
            uuid: $uuid,
            slug: $slug,
            parentSubscriptionUuid: $parentItemUuid?->toString(),
            subscriptionUuid: null,
            billingPeriod: 1,
            contractPeriod: 1,
            domain: null,
            status: null,
            children: null,
            oneTimeServices: null,
            experimentSlug: null
        );
    }
}
