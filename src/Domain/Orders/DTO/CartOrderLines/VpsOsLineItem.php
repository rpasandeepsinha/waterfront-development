<?php

declare(strict_types=1);

namespace Waterfront\Domain\Orders\DTO\CartOrderLines;

use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Waterfront\Domain\Orders\DTO\CartOrderSubscription;
use Waterfront\Domain\Products\Enums\ProductPriceType;

class VpsOsLineItem extends LineItem
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
        #[Groups(['meta_data'])]
        #[SerializedName('ssh_key_uuid')]
        public ?string $sshKeyUuid,
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
