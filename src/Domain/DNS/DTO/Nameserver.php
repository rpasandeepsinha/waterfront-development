<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\DTO;

class Nameserver
{
    public function __construct(
        public readonly string $hostname,
        public readonly ?string $ipv4 = null,
        public readonly ?string $ipv6 = null
    ) {
    }
}
