<?php

declare(strict_types=1);

namespace Waterfront\Infra\AcronisClient\Enums\OfferingItems;

enum OfferingItemType: string
{
    case COUNT = 'count';
    case FEATURE = 'feature';
    case INFRA = 'infra';
}
