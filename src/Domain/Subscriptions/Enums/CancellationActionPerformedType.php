<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Enums;

enum CancellationActionPerformedType: string
{
    case STARTED = 'start';
    case CONTINUE = 'continue';
    case ABANDONED = 'abandoned';
    case PREVIOUS = 'previous';
}
