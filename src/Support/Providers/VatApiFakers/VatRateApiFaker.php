<?php

declare(strict_types=1);

namespace Waterfront\Support\Providers\VatApiFakers;

use DateTimeImmutable;
use SandwaveIo\Vat\VatRates\ResolvesVatRates;

class VatRateApiFaker implements ResolvesVatRates
{
    public function getDefaultVatRateForCountry(string $countryCode, ?DateTimeImmutable $dateTime = null): ?float
    {
        if ($countryCode === 'NL') {
            return 21.0;
        }

        if ($countryCode === 'BE') {
            return 21.0;
        }

        if ($countryCode === 'DE') {
            return 19.0;
        }

        if ($countryCode === 'BG') {
            return 20.0;
        }

        return 0.0;
    }
}
