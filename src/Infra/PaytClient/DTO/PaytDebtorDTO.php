<?php

declare(strict_types=1);

namespace Waterfront\Infra\PaytClient\DTO;

readonly class PaytDebtorDTO
{
    public function __construct(
        public string $id,
        public string $debtorNumber,
        public string $name,
        public ?string $callPhoneNumber,
        public ?string $smsPhoneNumber,
        public ?string $primaryEmailAddress,
        public ?string $invoiceEmailAddress,
        public ?string $debtorIdentifier,
        public ?string $languageCode,
        public ?PaytDebtorPostalAddressDTO $postalAddress,
        public ?string $administrationId,
    ) {
    }
}
