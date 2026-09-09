<?php

declare(strict_types=1);

namespace Waterfront\Domain\OneTimeServices\Enums;

enum OneTimeServiceStatus: string
{
    case OPEN = 'open';
    case IN_PROGRESS = 'in_progress';
    case DONE = 'done';
}
