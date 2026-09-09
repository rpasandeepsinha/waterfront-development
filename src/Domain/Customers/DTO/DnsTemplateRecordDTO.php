<?php

declare(strict_types=1);

namespace Waterfront\Domain\Customers\DTO;

readonly class DnsTemplateRecordDTO
{
    public function __construct(
        public string $referenceRecordId,
        public string $name,
        public string $type,
        public string $content,
        public int|null $priority,
        public int|null $weight,
        public int|null $port,
        public int $ttl,
        public bool $disabled = false,
    ) {
    }
}
