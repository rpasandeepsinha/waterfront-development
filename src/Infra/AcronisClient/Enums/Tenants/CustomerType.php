<?php

declare(strict_types=1);

namespace Waterfront\Infra\AcronisClient\Enums\Tenants;

enum CustomerType: string
{
    case DEFAULT = 'default';
    case ENTERPRISE = 'enterprise';
    case CONSUMER = 'consumer';
    case SMALL_OFFICE = 'small_office';
    case TI_UNIFIED = 'ti_unified';
}
