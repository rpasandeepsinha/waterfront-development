<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Enums;

enum ProductChangeType: string
{
    case UPGRADE = 'upgrade';
    case DOWNGRADE = 'downgrade';
    case REINSTALL = 'reinstall';
}
