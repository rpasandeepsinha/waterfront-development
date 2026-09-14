<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Enums;

use SandwaveIo\HarborMessages\Message\Enum\InvoiceLineCreditReason;

/**
 * NOTICE:  Must be kept 1-to-1 with InvoiceLineCreditReason.
 *          Any additions made to SubscriptionCancelReason must also propagate to InvoiceLineCreditReason,
 *          but additions made to InvoiceLineCreditReason don't have to propagate to SubscriptionCancelReason.
 *
 * @see InvoiceLineCreditReason
 */
enum SubscriptionCancelReason: string
{
    case REASON_WET_VAN_DAM = 'reason_wet_van_dam';
    case REASON_REVOCATION = 'reason_revocation';
    case REASON_DISSATISFIED = 'reason_dissatisfied';
    case REASON_CANCELLATION = 'reason_cancellation';
    case REASON_CANCELLATION_RENEWAL = 'reason_cancellation_renewal';
    case REASON_FAILURE = 'reason_failure';
    case REASON_TRANSFER = 'reason_transfer';
    case REASON_BAD_DEBT = 'reason_bad_debt';
    case REASON_OTHER = 'reason_other';
    case REASON_ABUSE = 'reason_abuse';
    case REASON_DOMAIN_TRANSFERRED_AWAY = 'reason_domain_transferred_away';

    public function enforcesToCancelImmediately(): bool
    {
        return match ($this) {
            self::REASON_REVOCATION, self::REASON_FAILURE => true,
            default => false,
        };
    }

    public function enforcesToCreditFully(): bool
    {
        return match ($this) {
            self::REASON_REVOCATION,
            self::REASON_FAILURE,
            self::REASON_DISSATISFIED,
            self::REASON_CANCELLATION_RENEWAL,
                => true,
            default => false,
        };
    }

    public function allowedToCredit(): bool
    {
        return match ($this) {
            self::REASON_DOMAIN_TRANSFERRED_AWAY, self::REASON_TRANSFER, self::REASON_BAD_DEBT => false,
            default => true,
        };
    }
}
