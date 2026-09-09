<?php

declare(strict_types=1);

namespace Waterfront\Infra\AcronisClient\Enums;

enum TrusteeType: string
{
    case USER = 'user';
    case USER_GROUP = 'user_group';
    case CLIENT = 'client';
}
