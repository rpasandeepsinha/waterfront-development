<?php

declare(strict_types=1);

namespace Waterfront\Infra\PaytClient\DTO;

readonly class PaytDebtorPostalAddressDTO
{
    public function __construct(
        public ?string $city,
        public ?string $countryCode,
        public ?string $postalCode,
        public ?string $region,
        public ?string $street1,
        public ?string $street2,
    ) {
    }
}
