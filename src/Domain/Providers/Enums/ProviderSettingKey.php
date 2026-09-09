<?php

declare(strict_types=1);

namespace Waterfront\Domain\Providers\Enums;

enum ProviderSettingKey: string
{
    case QUOTA = 'quota';
    case LIMIT = 'limit';
    case DEFAULTSERVERID = 'default-server-id';
    case BRANDREFERENCE = 'brand-reference';
    case PACKAGEREFERENCE = 'package-reference';
}
