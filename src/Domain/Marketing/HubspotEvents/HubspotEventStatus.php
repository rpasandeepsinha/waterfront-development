<?php

declare(strict_types=1);

namespace Waterfront\Domain\Marketing\HubspotEvents;

enum HubspotEventStatus: string
{
    case FAILED = 'failed';
    case PENDING = 'pending';
    case SUCCESS = 'success';
}
