<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Enums;

enum ManualMigrationDomainProvider: string
{
    case OPENPROVIDER = 'openprovider';
    case RTR = 'realtime_register';
    case RTRNEW = 'realtime_register_new';
}
