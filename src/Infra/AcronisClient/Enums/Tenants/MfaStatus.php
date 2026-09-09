<?php

declare(strict_types=1);

namespace Waterfront\Infra\AcronisClient\Enums\Tenants;

enum MfaStatus: string
{
    case DISABLED = 'disabled';
    case FORCIBLY_DISABLED = 'forcibly_disabled';
    case SETUP_REQUIRED = 'setup_required';
    case ENABLED = 'enabled';
}
