<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminJsonClient\DTO;

class DirectAdminServer
{
    public function __construct(
        public string $baseUrl,
        public string $username,
        public string $password, // Can also be a login key
        public int $port,
        public bool $verifySsl = true,
        public ?string $asUser = null,
    ) {
    }
}
