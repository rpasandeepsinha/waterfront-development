<?php

declare(strict_types=1);

namespace Waterfront\Infra\RtrClient\Services\Enums;

// See: https://github.com/sandwave-io/realtimeregister-php/blob/master/src/Domain/Enum/LogStatusEnum.php
enum LogStatus: string
{
    public const STATUS_PENDINGWHOIS = 'pendingwhois';
    public const STATUS_PENDING_APPROVAL_AUTHORIZED_CONTACT = 'pendingfoa';
    public const STATUS_PENDINGVALIDATION = 'pendingvalidation';
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_FAILED = 'failed';
    public const STATUS_COMPLETED = 'completed';

    /**
     * @return string[]
     */
    public static function getPendingStatuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_PENDINGVALIDATION,
            self::STATUS_PENDING_APPROVAL_AUTHORIZED_CONTACT,
            self::STATUS_PENDINGWHOIS,
        ];
    }
}
