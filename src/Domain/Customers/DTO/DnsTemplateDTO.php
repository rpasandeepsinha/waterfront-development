<?php

declare(strict_types=1);

namespace Waterfront\Domain\Customers\DTO;

readonly class DnsTemplateDTO
{
    /**
     * @param array<int, DnsTemplateRecordDTO> $records
     */
    public function __construct(
        public string $referenceTemplateId,
        public string $name,
        public array $records = [],
    ) {
    }
}
