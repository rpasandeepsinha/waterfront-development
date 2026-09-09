<?php

declare(strict_types=1);

namespace Waterfront\Infra\AcronisClient\DTO\OfferingItems;

use Symfony\Component\Serializer\Attribute\Groups;

#[Groups(['put'])]
class Quota
{
    public function __construct(
        public ?int $version,
        public ?int $value,
        public ?int $overage
    ) {
    }

    public static function limited(int $value, ?int $overage = null): self
    {
        return new self(version: null, value: $value, overage: $overage);
    }
}
