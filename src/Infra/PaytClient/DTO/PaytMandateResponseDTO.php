<?php

declare(strict_types=1);

namespace Waterfront\Infra\PaytClient\DTO;

readonly class PaytMandateResponseDTO
{
    public function __construct(
        public ?string $id,
        public ?string $mandateIdentifier,
    ) {
    }
}
