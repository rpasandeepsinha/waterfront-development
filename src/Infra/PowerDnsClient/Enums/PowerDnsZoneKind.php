<?php

declare(strict_types=1);

namespace Waterfront\Infra\PowerDnsClient\Enums;

enum PowerDnsZoneKind: string
{
    case NATIVE = 'Native';
    case MASTER = 'Master';
    case SLAVE = 'Slave';
    case PRODUCER = 'Producer';
    case CONSUMER = 'Consumer';
}
