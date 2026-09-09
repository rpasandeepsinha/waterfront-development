<?php

declare(strict_types=1);

namespace Waterfront\Infra\RtrClient\Enums;

enum SubjectStatusType: string
{
    case OK = 'OK';
    case FAILED = 'FAILED';
    case PENDING = 'PENDING';
    case CANCELLED = 'CANCELLED';
}
