<?php

declare(strict_types=1);

namespace Waterfront\Domain\Orders\DTO\CartOrderLines;

use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Attribute\Groups;
use Waterfront\Domain\Orders\DTO\CartOrderSubscription;
use Waterfront\Domain\Products\Enums\ProductPriceType;

class ExtensionLineItem extends LineItem
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
        ?string $experimentSlug,
        #[Groups(['meta_data'])]
        public ?string $transferSecret,
        #[Groups(['meta_data'])]
        public ?bool $privateWhois,
        #[Groups(['meta_data'])]
        public ?int $contactId
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
