<?php

declare(strict_types=1);

namespace Waterfront\Infra\AcronisClient\DTO\Responses\OfferingItems;

class OfferingItemPrice
{
    public function __construct(
        public string $applicationId,
        public string $name,
        public string $price,
        public int $version,
        public ?string $infraId = null,
    ) {
    }
}
