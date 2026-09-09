<?php

declare(strict_types=1);

namespace Waterfront\Domain\RetentionToolkit\Enums;

enum CustomerType: string
{
    case CONSUMER = 'consumer';
    case BUSINESS = 'business';
}
