<?php

declare(strict_types=1);

namespace Waterfront\Infra\PowerDnsClient\Enums;

enum PowerDnsRecordChangeType: string
{
    case REPLACE = 'REPLACE';
    case DELETE = 'DELETE';
}
