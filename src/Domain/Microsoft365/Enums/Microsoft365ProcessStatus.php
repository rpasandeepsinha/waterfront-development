<?php

declare(strict_types=1);

namespace Waterfront\Domain\Microsoft365\Enums;

enum Microsoft365ProcessStatus: string
{
    case INITIATED = 'initiated';
    case CUSTOMER_CREATED = 'customer-created';
    case ACTIVE = 'active';
    case FAILED = 'failed';
}
