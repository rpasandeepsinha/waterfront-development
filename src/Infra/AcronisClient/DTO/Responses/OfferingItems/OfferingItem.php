<?php

declare(strict_types=1);

namespace Waterfront\Infra\AcronisClient\DTO\Responses\OfferingItems;

use Waterfront\Infra\AcronisClient\DTO\OfferingItems\Quota;
use Waterfront\Infra\AcronisClient\Enums\OfferingItems\OfferingItemStatus;

class OfferingItem
{
    public function __construct(
        public OfferingItemStatus $status,
        public ?Quota $quota,
    ) {
    }
}
