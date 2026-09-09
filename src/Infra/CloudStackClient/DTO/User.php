<?php

declare(strict_types=1);

namespace Waterfront\Infra\CloudStackClient\DTO;

class User
{
    public function __construct(
        public string $id,
        public string $username,
        public string $accountId,
        public string $domainId,
    ) {
    }
}
