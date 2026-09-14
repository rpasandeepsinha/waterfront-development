<?php

declare(strict_types=1);

namespace Waterfront\Infra\PaytClient\DTO;

readonly class PaytPaginationResponseDTO
{
    public function __construct(
        public ?string $cursor = null,
    ) {
    }
}
