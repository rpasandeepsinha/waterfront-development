<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Enums;

enum MigrationSubscriptionStatus: string
{
    case FAILED = 'failed';
    case EXECUTED = 'executed';
    case NOT_EXECUTED = 'not_executed';
}
