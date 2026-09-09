<?php

declare(strict_types=1);

namespace Waterfront\Infra\PaytClient\DTO;

readonly class PaytMandateResponseDTO
{
    public function __construct(
        public string|null $id,
        public string|null $mandateIdentifier,
    ) {
    }
}
