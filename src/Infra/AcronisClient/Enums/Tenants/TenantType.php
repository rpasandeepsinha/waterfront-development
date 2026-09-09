<?php

declare(strict_types=1);

namespace Waterfront\Infra\AcronisClient\Enums\Tenants;

enum TenantType: string
{
    case ROOT = 'root';
    case PARTNER = 'partner';
    case FOLDER = 'folder';
    case CUSTOMER = 'customer';
    case UNIT = 'unit';
}
