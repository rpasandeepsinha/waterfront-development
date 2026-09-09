<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Entities\DnsRecords;

class ARecord extends AbstractRecord
{
    public function __construct(string $name, string $content, int $ttl, bool $disabled = false)
    {
        parent::__construct('A', $name, $content, $ttl, $disabled);
    }
}
