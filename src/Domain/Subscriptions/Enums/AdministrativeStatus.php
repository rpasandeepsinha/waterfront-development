<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Enums;

enum AdministrativeStatus: string
{
    case ACTIVE = 'active';
    case CANCELED = 'canceled';
    case ARCHIVED = 'archived';
    case ARCHIVING = 'archiving';
    case EXPIRED = 'expired';

    /** @deprecated This status was used in the m365 migration to have it be renewed but not invoiced. Do not use this anymore.*/
    case INACTIVE = 'inactive';
    case SUSPENDED = 'suspended';

    /**
     * @return string[]
     */
    public static function getIneligibleForSuspension(): array
    {
        return [
            self::ARCHIVED->value,
            self::EXPIRED->value,
            self::INACTIVE->value,
            self::SUSPENDED->value,
        ];
    }

    /**
     * @return string[]
     */
    public static function administrativelyEnded(): array
    {
        return [
            self::ARCHIVED->value,
            self::EXPIRED->value,
        ];
    }
}
