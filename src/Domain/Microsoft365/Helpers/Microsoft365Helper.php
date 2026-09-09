<?php

declare(strict_types=1);

namespace Waterfront\Domain\Microsoft365\Helpers;

class Microsoft365Helper
{
    private const string KPN_CUSTOMER_ID_PREFIX = 'CID';

    public static function customerIdToStringWithPrefix(string|int $customerId): string
    {
        $customerId = (string) $customerId;
        if (str_starts_with($customerId, self::KPN_CUSTOMER_ID_PREFIX)) {
            return $customerId;
        }

        return self::KPN_CUSTOMER_ID_PREFIX . $customerId;
    }

    public static function customerIdToStringWithoutPrefix(string|int|null $customerId): string
    {
        return ltrim((string) $customerId, self::KPN_CUSTOMER_ID_PREFIX);
    }

    public static function customerIdToInt(string|int|null $customerId): int
    {
        return (int) self::customerIdToStringWithoutPrefix($customerId);
    }
}
