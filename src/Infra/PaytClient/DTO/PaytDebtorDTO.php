<?php

declare(strict_types=1);

namespace Waterfront\Infra\PaytClient\DTO;

readonly class PaytDebtorDTO
{
    public function __construct(
        public string $id,
        public string $debtorNumber,
        public string $name,
        public string|null $callPhoneNumber,
        public string|null $smsPhoneNumber,
        public string|null $primaryEmailAddress,
        public string|null $invoiceEmailAddress,
        public string|null $debtorIdentifier,
        public string|null $languageCode,
        public PaytDebtorPostalAddressDTO|null $postalAddress,
        public string|null $administrationId,
    ) {
    }
}
