<?php

declare(strict_types=1);

namespace Waterfront\Infra\CloudStackClient\DTO;

class UserKeys
{
    public function __construct(
        public string $apiKey,
        public string $secretKey
    ) {
    }
}
