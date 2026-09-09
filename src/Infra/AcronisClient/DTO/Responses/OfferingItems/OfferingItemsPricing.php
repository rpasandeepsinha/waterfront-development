<?php

declare(strict_types=1);

namespace Waterfront\Infra\AcronisClient\DTO\Responses\OfferingItems;

class OfferingItemsPricing
{
    /**
     * @param list<OfferingItemPrice> $items
     */
    public function __construct(
        public array $items,
    ) {
    }
}
