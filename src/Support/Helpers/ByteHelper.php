<?php

declare(strict_types=1);

namespace Waterfront\Support\Helpers;

class ByteHelper
{
    public const int BYTES_IN_GIB = 1024 * 1024 * 1024;

    public static function bytesToGiB(int $bytes): float
    {
        return round(max(0, $bytes) / self::BYTES_IN_GIB, 2);
    }

    public static function giBToBytes(int|float $gib): int
    {
        return (int) round(max(0, $gib) * self::BYTES_IN_GIB);
    }
}
