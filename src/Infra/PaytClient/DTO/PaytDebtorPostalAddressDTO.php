<?php

declare(strict_types=1);

namespace Waterfront\Infra\PaytClient\DTO;

readonly class PaytDebtorPostalAddressDTO
{
    public function __construct(
        public string|null $city,
        public string|null $countryCode,
        public string|null $postalCode,
        public string|null $region,
        public string|null $street1,
        public string|null $street2,
    ) {
    }
}
