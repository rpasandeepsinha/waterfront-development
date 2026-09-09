<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Enums;

enum CancellationOfferType: string
{
    case PHONE = 'phone';
    case PERCENTAGE_DISCOUNT = 'percentage-discount';

    /** @deprecated No longer offered, but old offers are still stored */
    case PERIOD_DISCOUNT = 'period-discount';
}
