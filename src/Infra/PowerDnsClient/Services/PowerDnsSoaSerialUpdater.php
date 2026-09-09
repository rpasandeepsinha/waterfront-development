<?php

declare(strict_types=1);

namespace Waterfront\Infra\PowerDnsClient\Services;

use Carbon\CarbonImmutable;
use Waterfront\Infra\PowerDnsClient\Exceptions\PdnsNotSOARecordException;

class PowerDnsSoaSerialUpdater
{
    private const int PART_SERIAL = 2;

    public static function increaseSoaSerial(string $soaContent): string
    {
        $soaContentExploded = explode(' ', $soaContent);

        // Array items: MNAME RNAME SERIAL REFRESH RETRY EXPIRE TTL
        if (count($soaContentExploded) !== 7) {
            throw new PdnsNotSOARecordException($soaContent);
        }

        $originalSoaSerial = $soaContentExploded[self::PART_SERIAL];

        // when $originalSoaSerial is a string for whatever reason this will be 0, but that's okay
        $dateFromSerial = substr($originalSoaSerial, 0, 8);
        $dateToday = CarbonImmutable::now()->format('Ymd');

        if ($dateToday !== $dateFromSerial) {
            // Need to start over if the day has changed
            $serial = 1;
        } else {
            // Same day, increase the serial up to 99
            $serial = abs((int) substr($originalSoaSerial, 8));

            $serial++;

            if ($serial > 99) {
                // After 99 we go back to 1
                $serial = 1;
            }
        }

        $serial = str_pad((string) $serial, 2, '0', STR_PAD_LEFT);

        $soaContentExploded[self::PART_SERIAL] = $dateToday . $serial;

        return implode(' ', $soaContentExploded);
    }
}
