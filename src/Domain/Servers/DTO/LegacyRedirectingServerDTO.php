<?php

declare(strict_types=1);

namespace Waterfront\Domain\Servers\DTO;

readonly class LegacyRedirectingServerDTO
{
    public function __construct(
        public string $hostname,
        public string $ipv4,
        public ?string $ipv6,
        public string $originalBusinessUnit,
    ) {
    }
}
