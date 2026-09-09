<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Enums;

enum DnsChangeType: string
{
    case DELETED = 'deleted';
    case CREATED = 'created';
}
