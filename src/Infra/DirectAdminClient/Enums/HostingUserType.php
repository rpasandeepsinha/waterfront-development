<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminClient\Enums;

enum HostingUserType: string
{
    case USER     = 'user';
    case RESELLER = 'reseller';
    case ADMIN    = 'admin';
}
