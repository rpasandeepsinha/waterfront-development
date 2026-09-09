<?php

declare(strict_types=1);

namespace Waterfront\Domain\Customers\DTO;

use Propaganistas\LaravelPhone\PhoneNumber;

readonly class PhoneDTO
{
    private string $countryCode;

    private string $areaCode;

    private string $number;

    public function __construct(string $phone)
    {
        $phoneFormatted = new PhoneNumber($phone)->formatInternational();

        $phoneParts = explode(' ', $phoneFormatted, 2);

        $this->countryCode = ltrim($phoneParts[0], '+');

        // necessary as US numbers are setup with dashes instead of spaces
        array_shift($phoneParts);
        if (str_contains($phoneParts[0], '-')) {
            $endParts = explode('-', $phoneParts[0]);
        } elseif (str_contains($phoneParts[0], ' ')) {
            $endParts = explode(' ', $phoneParts[0]);
        } else {
            $endParts = [substr(implode('', $phoneParts), 0, 3), substr(implode('', $phoneParts), 3)];
        }

        /** @var string $areaCode */
        $areaCode = array_shift($endParts);

        $this->areaCode = $areaCode;
        $this->number = implode('', $endParts);
    }

    public function getCountryCode(): string
    {
        return $this->countryCode;
    }

    public function getAreaCode(): string
    {
        return $this->areaCode;
    }

    public function getNumber(): string
    {
        return $this->number;
    }
}
