<?php

declare(strict_types=1);

namespace Waterfront\Domain\Providers\Enums;

enum ProviderSlug: string
{
    case ACRONIS = 'acronis';
    case DIRECTADMIN = 'directadmin';
    case PLESK = 'integratedservice';
    case BASEKIT = 'basekit';
    case REALTIME_REGISTER = 'realtime_register';
    case PLACEHOLDER = 'placeholder';
    case OPEN_PROVIDER = 'openprovider';
    case XOLPHIN = 'xolphin';
}
