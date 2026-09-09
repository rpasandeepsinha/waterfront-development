<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Enums;

enum SubscriptionCancelType: string
{
    case CANCEL_END_DATE = 'cancel_end_date';
    case CANCEL_OTHER = 'cancel_other';
    case CANCEL_DOWNGRADE = 'cancel_downgrade';

    public function allowedToCredit(): bool
    {
        return match ($this) {
            self::CANCEL_END_DATE => false,
            default => true,
        };
    }
}
