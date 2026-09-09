<?php

declare(strict_types=1);

namespace Waterfront\Domain\AuditLogs\Enums;

enum AuditLogUserType: string
{
    case CONSOLE = 'console';
    case USER = 'App\Models\User';
}
