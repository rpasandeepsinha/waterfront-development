<?php

declare(strict_types=1);

namespace Waterfront\Infra\AcronisClient\DTO\Responses\Users;

use SensitiveParameter;

class OneTimeToken
{
    public function __construct(
        #[SensitiveParameter]
        public string $ott,
    ) {
    }
}
