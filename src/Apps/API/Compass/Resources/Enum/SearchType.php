<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\Enum;

enum SearchType: string
{
    case CUSTOMER = 'customer';
    case SUBSCRIPTION  = 'subscription';
    case DOMAIN      = 'domain';
    case PRODUCT = 'product';
}
