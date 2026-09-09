<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\DTO;

use Waterfront\Domain\DNS\Enums\DnsChangeType;
use Waterfront\Domain\DNS\Enums\DnsRecordType;

readonly class DnsRecordChangeDTO
{
    public function __construct(
        public DnsRecordType $record_type,
        public DnsChangeType $change_type,
        public string $name,
        public string $content,
        public int $ttl,
        public ?int $priority = null,
        public ?int $weight = null,
        public ?int $port = null,
    ) {
    }
}
