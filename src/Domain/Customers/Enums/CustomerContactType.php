<?php

declare(strict_types=1);

namespace Waterfront\Domain\Customers\Enums;

enum CustomerContactType: string
{
    case DEFAULT = 'default';
    case FINANCIAL = 'financial';
    case TECHNICAL = 'technical';
}
