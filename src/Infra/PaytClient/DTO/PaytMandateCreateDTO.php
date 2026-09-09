<?php

declare(strict_types=1);

namespace Waterfront\Infra\PaytClient\DTO;

use Waterfront\Infra\PaytClient\Enums\PaytProviderCode;

readonly class PaytMandateCreateDTO
{
    public function __construct(
        public string $bankAccountName,
        public string $bankAccountNumber,
        public string $mandateIdentifier,
        public string $debtorCode,
        public string $customerIdentifier,
        public PaytProviderCode $providerCode = PaytProviderCode::MOLLIE,
    ) {
    }
}
