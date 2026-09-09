<?php

declare(strict_types=1);

namespace Waterfront\Infra\RtrClient\DTO;

class DcvDetails
{
    public function __construct(
        public readonly string $status,
        public readonly string $caaRecordStatus,
        public readonly string $dnsRecord,
        public readonly string $dnsType,
        public readonly string $dnsContent,
    ) {
    }
}
