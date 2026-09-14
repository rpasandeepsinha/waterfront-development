<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Requests\CustomerWallet\Rules;

use Illuminate\Container\Container;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Infra\Validation\AbstractValidator;

/**
 * Checks whether an IBAN (International Bank Account Number (ISO 13616:2020)) has a valid
 * format using to ISO/IEC 7064 to calculating check digit characters. It does not check the
 * additional constraints used by the 57+ countries that use IBAN.
 * Digit check: https://www.iso.org/obp/ui/#iso:std:iso-iec:7064:ed-1:v1:en
 * UN implementation: https://www.tbg5-finance.org/?ibandocs.shtml
 * Known formats, as of 2021, r91: https://www.swift.com/resource/iban-registry-pdf.
 */
class IBAN extends AbstractValidator
{
    /**
     * @var array<string,string>
     */
    private const array CountryPatterns = [
        //Default pattern
        null => '/^[A-Z]{2}[0-9]{2}[A-Z0-9]{1,30}$/',
        //Country specific
        'NL' => '/^NL\d{2}[A-Z]{4}\d{10}$/',
    ];

    public function __construct(
        private readonly ?string $country = null,
    ) {
    }

    protected function passes(string $attribute, mixed $value): bool
    {
        if (! is_string($value) || strlen(trim($value)) === 0) {
            return false;
        }

        $value = strtoupper($value);

        $country = null;
        if ($this->country !== null && key_exists($this->country, IBAN::CountryPatterns)) {
            $country = $this->country;
        }

        $countrySpecificRegex = IBAN::CountryPatterns[$country];
        if (preg_match($countrySpecificRegex, $value) === 0) {
            return false;
        }

        // Validation rules with example:
        // 1. Format check:                                         regex: [A-Z]{2}[0-9]{2}[A-Z0-9]{1,30}
        // 2. Original IBAN:                                        GB82 WEST 1234 5698 7654 32
        // 3. Rearrange, move first 4 characters to back:           W E S T12345698765432 G B82
        // 4. Replace all letters with numbers (A=10, B=11):	    3214282912345698765432161182
        // 5. Use output as integer and do mod 97:	                3214282912345698765432161182 modulo 97 == 1

        $prefix = substr($value, 0, 4);
        $ibanStr = str_split(substr($value, 4) . $prefix);

        $ibanNumStr = '';
        foreach ($ibanStr as $character) {
            $c = ord($character);
            if ($c >= ord('0') && $c <= ord('9')) {
                $ibanNumStr .= $character;
            } elseif ($c >= ord('A') && $c <= ord('Z')) {
                $ibanNumStr .= strval($c - ord('A') + 10);
            } else {
                return false;
            }
        }

        // IBANs that are converted to number are Bigint. As PHP does not support
        // big ints the workaround is to use the Mathematical extensions and use
        // strings. More information can be found at PHP's BCMath Arbitrary Precision
        // Mathematics section.
        // https://www.php.net/manual/en/book.bc.php
        return bcmod($ibanNumStr, '97') === '1';
    }

    protected function message(): string
    {
        /** @var TranslatorInterface $translator */
        $translator = Container::getInstance()->make(TranslatorInterface::class);

        return $translator->translate('validation.iban_number');
    }
}
