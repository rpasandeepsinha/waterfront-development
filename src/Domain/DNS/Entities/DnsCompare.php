<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Entities;

use Waterfront\Domain\DNS\Interfaces\DnsRecordInterface;

/**
 * Helper class to compare dns records.
 */
class DnsCompare
{
    /**
     * Returns true if both dns records are the same. The check is case insensitive.
     */
    public static function equals(DnsRecordInterface $contract1, DnsRecordInterface $contract2): bool
    {
        return $contract1->toArray() === $contract2->toArray();
    }
}
