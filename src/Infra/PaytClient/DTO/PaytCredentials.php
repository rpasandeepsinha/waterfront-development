<?php

declare(strict_types=1);

namespace Waterfront\Infra\PaytClient\DTO;

use SensitiveParameter;

readonly class PaytCredentials
{
    public function __construct(
        #[SensitiveParameter]
        public string $apiKey,
        public string $administrationId,
    ) {
    }
}
