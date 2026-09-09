<?php

declare(strict_types=1);

namespace Waterfront\Infra\AcronisClient\DTO\Requests\OfferingItems;

use Symfony\Component\Serializer\Attribute\Groups;

#[Groups(['put'])]
class OfferingItemsPricingItem
{
    public ?string $infraId;

    public function __construct(
        public string $applicationId,
        public string $name,
        public string $price,
        public int $version,
    ) {
    }
}
