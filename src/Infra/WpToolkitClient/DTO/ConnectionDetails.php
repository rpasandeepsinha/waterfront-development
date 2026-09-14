<?php

declare(strict_types=1);

namespace Waterfront\Infra\WpToolkitClient\DTO;

use SensitiveParameter;

class ConnectionDetails
{
    public function __construct(
        public readonly string $pleskHost,
        public readonly int $pleskPort = 8443,
        public readonly ?string $username = null,
        #[SensitiveParameter]
        public readonly ?string $password = null,
        public readonly ?string $token = null,
    ) {
    }
}
