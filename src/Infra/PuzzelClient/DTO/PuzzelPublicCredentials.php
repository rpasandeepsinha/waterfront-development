<?php

declare(strict_types=1);

namespace Waterfront\Infra\PuzzelClient\DTO;

readonly class PuzzelPublicCredentials
{
    public function __construct(
        public string $baseUrl,
        public string $clientId,
        public string $clientSecret,
    ) {
    }
}
