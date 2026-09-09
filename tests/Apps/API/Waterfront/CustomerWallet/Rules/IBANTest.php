<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\CustomerWallet\Rules;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Waterfront\Requests\CustomerWallet\Rules\IBAN;

#[CoversClass(IBAN::class)]
class IBANTest extends IntegrationTestCase
{
    #[DataProvider('getValidIBANs')]
    #[Test]
    public function validIBAN(string $iban): void
    {
        $ibanRule = new IBAN();
        $ibanRule->validate('', $iban, self::assertClosureIsCalled(false));
    }

    #[DataProvider('getValidCountryIBANs')]
    #[Test]
    public function validCountryIBAN(string $country, string $iban): void
    {
        $ibanRule = new IBAN($country);
        $ibanRule->validate('', $iban, self::assertClosureIsCalled(false));
    }

    #[DataProvider('getInvalidIBANs')]
    #[Test]
    public function invalidIBAN(string $iban): void
    {
        $ibanRule = new IBAN();
        $ibanRule->validate('', $iban, self::assertClosureIsCalled(true));
    }

    #[DataProvider('getInvalidCountryIBANs')]
    #[Test]
    public function invalidCountryIBAN(string $country, string $iban): void
    {
        $ibanRule = new IBAN($country);
        $ibanRule->validate('', $iban, self::assertClosureIsCalled(true));
    }

    /**
     * @return array<array<string>>
     */
    public static function getValidIBANs(): array
    {
        return [
            [ 'AL35202111090000000001234567' ],
            [ 'AD1400080001001234567890' ],
            [ 'DK9520000123456789' ],
            [ 'FI1410093000123458' ],
            [ 'IE64IRCE92050112345678' ],
            [ 'NL02ABNA0123456789' ],
        ];
    }

    /**
     * @return array<array<string>>
     */
    public static function getValidCountryIBANs(): array
    {
        return [
            [ 'NL', 'NL91ABNA0417164300' ],
            [ 'NL', 'NL02ABNA0123456789' ],
        ];
    }

    /**
     * @return array<array<string>>
     */
    public static function getInvalidIBANs(): array
    {
        return [
            [ 'AL35202161090000000001234567' ],
            [ 'AD1400080401001234567890' ],
            [ 'DK9520004123456789' ],
            [ 'FI1410093030123458' ],
            [ 'IE64IRCE92040112345678' ],
            [ 'NL02ABNA0123656789' ],
            [ 'NL02ABNA012ZZZ6789' ],
            [ 'Random numbers' ],
            [ 'Test' ],
        ];
    }

    /**
     * @return array<array<string>>
     */
    public static function getInvalidCountryIBANs(): array
    {
        return [
            [ 'NL', 'NL91ABNA0457174300' ],
            [ 'NL', 'DK9520000123456789' ],
            [ 'NL', 'test' ],
        ];
    }
}
