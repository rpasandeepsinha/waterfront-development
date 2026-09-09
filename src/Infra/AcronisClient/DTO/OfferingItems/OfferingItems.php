<?php

declare(strict_types=1);

namespace Waterfront\Infra\AcronisClient\DTO\OfferingItems;

use Symfony\Component\Serializer\Attribute\Groups;

class OfferingItems
{
    /**
     * @param array<OfferingItem>       $offeringItems This key is used when sending (updated) items to the API
     * @param array<OfferingItem>|null  $items         This key is used when fetching items from the API
     * @param array<string, mixed>|null $paging
     */
    public function __construct(
        #[Groups(['put'])]
        public ?bool $checkUsage,
        #[Groups(['put'])]
        public ?array $offeringItems,
        public ?array $items = null,
        public ?string $timestamp = null,
        public ?array $paging = null,
    ) {
    }
}
