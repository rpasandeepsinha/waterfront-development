<?php

declare(strict_types=1);

namespace Waterfront\Domain\Lighthouse\DTO;

enum IdentityState: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
}
