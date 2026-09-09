<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\WpToolkit\DTO;

readonly class WpLogin
{
    public function __construct(
        public WpCredentials $credentials,
        public string $loginUrl,
    ) {
    }
}
