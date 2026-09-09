<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\DTO;

readonly class DnsRecord
{
    public function __construct(
        public int $siteId,
        public string $type,
        public string $host,
        public string $value,
        public ?int $opt,
    ) {
    }
}
