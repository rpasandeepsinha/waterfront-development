<?php

declare(strict_types=1);

namespace Waterfront\Domain\AuditLogs\Enums;

enum AuditLogEvent: string
{
    case CREATED = 'created';
    case UPDATED = 'updated';
    case SUSPENSION = 'suspension';
    case UNSUSPENSION = 'unsuspension';
}
