<?php

declare(strict_types=1);

namespace Waterfront\Infra\AcronisClient\Enums\OfferingItems;

enum OfferingItemStatus: int
{
    case NOT_ACTIVE = 0;
    case ACTIVE = 1;
}
