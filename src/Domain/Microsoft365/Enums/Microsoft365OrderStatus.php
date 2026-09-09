<?php

declare(strict_types=1);

namespace Waterfront\Domain\Microsoft365\Enums;

enum Microsoft365OrderStatus: string
{
    case PLACED = 'placed';
    case ACCEPTED = 'accepted';
    case MODIFY_PENDING = 'modify_pending';
    case MODIFIED = 'modified';
    case ACTIVE = 'active';
    case FAILED = 'failed';
    case TERMINATED = 'terminated';
}
