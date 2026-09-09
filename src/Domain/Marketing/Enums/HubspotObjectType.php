<?php

declare(strict_types=1);

namespace Waterfront\Domain\Marketing\Enums;

enum HubspotObjectType: string
{
    case CUSTOMER = 'customer';
    case SUBSCRIPTION = 'subscription';
    case ONE_TIME_SUBSCRIPTION = 'one-time-subscription';
}
