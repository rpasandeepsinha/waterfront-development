<?php

declare(strict_types=1);

namespace Waterfront\Domain\Orders\Enums;

enum OrderStatus: string
{
    case ON_HOLD = 'on_hold';
    case IN_PROGRESS = 'in_progress';
    case PROCESSED = 'processed';
    case ABUSE = 'abuse';
}
