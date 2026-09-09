<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\DTO;

class DnsRecord
{
    public function __construct(
        public readonly string $type,
        public readonly string $host,
        public readonly string $value,
    ) {
    }
}
