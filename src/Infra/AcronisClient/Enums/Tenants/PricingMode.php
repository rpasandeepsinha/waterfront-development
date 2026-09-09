<?php

declare(strict_types=1);

namespace Waterfront\Infra\AcronisClient\Enums\Tenants;

enum PricingMode: string
{
    case TRIAL = 'trial';
    case PRODUCTION = 'production';
    case SUSPENDED = 'suspended';
}
