<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Enums;

enum TechnicalStatus: string
{
    case ACT = 'ACT';
    case DEL = 'DEL';
    case FAI = 'FAI';

    case CANCELED = 'canceled';
    case DELETED = 'deleted';
    case DELETING = 'deleting';
    case DELETING_FAILED = 'deleting_failed';
    case DISABLED = 'disabled';
    case ERROR = 'error';
    case FAILED = 'failed';
    case OK = 'ok';
    case PENDING = 'pending';
    case REGISTRATION = 'registration';
    case SUSPENDED = 'suspended';
    case SUSPENDING = 'suspending';
    case SUSPENSION_FAILED = 'suspension_failed';
    case TRANSFER = 'transfer';
    case TRANSFER_FAILED = 'transfer_failed';
    case UNSUSPENDING = 'unsuspending';
    case UNSUSPENSION_FAILED = 'unsuspension_failed';
    case WAITING = 'waiting';
    case MIGRATING = 'MIGRATING';

    /**
     * @return self[]
     */
    public static function getInEligibleForSuspension(): array
    {
        return [
            self::SUSPENDING,
            self::DELETED,
            self::DELETING,
            self::FAILED,
            self::REGISTRATION,
            self::TRANSFER_FAILED,
            self::MIGRATING,
        ];
    }
}
