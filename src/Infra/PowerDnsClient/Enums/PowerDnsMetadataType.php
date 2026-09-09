<?php

declare(strict_types=1);

namespace Waterfront\Infra\PowerDnsClient\Enums;

enum PowerDnsMetadataType: string
{
    case ALLOW_AXFR_FROM = 'ALLOW-AXFR-FROM';
    case ALSO_NOTIFY = 'ALSO-NOTIFY';
    case SOA_EDIT = 'SOA-EDIT';
}
