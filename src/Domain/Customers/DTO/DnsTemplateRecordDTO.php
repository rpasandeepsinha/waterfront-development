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
        public ?int $priority,
        public ?int $weight,
        public ?int $port,
        public int $ttl,
        public bool $disabled = false,
    ) {
    }
}
