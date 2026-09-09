<?php

declare(strict_types=1);

namespace Waterfront\Infra\AcronisClient\DTO\Responses\Users;

class User
{
    public function __construct(
        public string $id,
        public string $login,
    ) {
    }
}
