<?php

declare(strict_types=1);

namespace Waterfront\Domain\Orders\DTO\CartOrderLines;

use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Waterfront\Domain\Orders\DTO\CartOrderSubscription;
use Waterfront\Domain\Products\Enums\ProductPriceType;

class Microsoft365LineItem extends LineItem
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
        ?string $domain,
        ?ProductPriceType $status,
        ?CartOrderSubscription $children,
        ?array $oneTimeServices,
        #[Groups(['meta_data'])]
        #[SerializedName('tenant_name')]
        public ?string $tenantName,
        #[Groups(['meta_data'])]
        #[SerializedName('tenant_id')]
        public ?string $tenantId,
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
        );
    }
}
