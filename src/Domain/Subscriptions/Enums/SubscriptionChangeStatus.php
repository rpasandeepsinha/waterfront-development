<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Enums;

enum SubscriptionChangeStatus: string
{
    case REQUESTED = 'requested';
    case INPROGRESS = 'in_progress';
    case EXECUTION_FAILED = 'execution_failed';
    case COMPLETED = 'completed';
}
