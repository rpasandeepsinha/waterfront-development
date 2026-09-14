<?php

declare(strict_types=1);

namespace Waterfront\Support\Providers\VatApiFakers;

use SandwaveIo\Vat\VatNumbers\ValidatesVatNumbers;

class VatNumberApiFaker implements ValidatesVatNumbers
{
    private const array VALID_COUNTRY_CODES = [
        'NL',
        'BE',
        'DE',
    ];

    public function verifyVatNumber(string $vatNumber, string $countryCode): bool
    {
        if ($vatNumber === '861350480B01') {
            return in_array($countryCode, self::VALID_COUNTRY_CODES, true);
        }

        return false;
    }
}
