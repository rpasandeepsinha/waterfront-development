<?php

declare(strict_types=1);

namespace Waterfront\Infra\AcronisClient\DTO\Requests\OfferingItems;

use Symfony\Component\Serializer\Attribute\Groups;

#[Groups(['put'])]
class OfferingItemsPricing
{
    /**
     * @param list<OfferingItemsPricingItem> $items
     */
    public function __construct(
        public array $items,
    ) {
    }
}
