<?php

declare(strict_types=1);

namespace Waterfront\Infra\AcronisClient\Enums\Tenants;

enum ContactType: string
{
    case LEGAL = 'legal';
    case PRIMARY = 'primary';
    case BILLING = 'billing';
    case TECHNICAL = 'technical';
    case MANAGEMENT = 'management';
}
