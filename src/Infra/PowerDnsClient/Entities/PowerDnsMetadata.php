<?php

declare(strict_types=1);

namespace Waterfront\Infra\PowerDnsClient\Entities;

readonly class PowerDnsMetadata
{
    /**
     * @param array<int, string> $metadata
     */
    public function __construct(
        public string $kind,
        public array $metadata,
        public string $type,
    ) {
    }
}
