<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminJsonClient\DTO;

use DateTimeImmutable;

class CreateLoginUrlResponse
{
    /**
     * @param string[] $allowNetworks
     */
    public function __construct(
        public array $allowNetworks,
        public DateTimeImmutable $created,
        public string $createdBy,
        public DateTimeImmutable $expires,
        public string $id,
        public ?string $redirectURL,
        public string $url
    ) {
    }
}
