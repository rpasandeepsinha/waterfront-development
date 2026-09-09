<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\WpToolkit\DTO;

use SensitiveParameter;

readonly class WpCredentials
{
    public function __construct(
        #[SensitiveParameter]
        public string $login,
        #[SensitiveParameter]
        public string $password,
    ) {
    }
}
