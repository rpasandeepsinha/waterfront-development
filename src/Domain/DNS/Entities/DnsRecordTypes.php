<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Entities;

class DnsRecordTypes
{
    /**
     * @return array<string, string>
     */
    public static function getModifiable(): array
    {
        return [
            'A' => 'A',
            'AAAA' => 'AAAA',
            'ALIAS' => 'ALIAS',
            'CAA' => 'CAA',
            'CNAME' => 'CNAME',
            'MX' => 'MX',
            'NS' => 'NS',
            'TXT' => 'TXT',
            'SPF' => 'SPF',
            'SRV' => 'SRV',
            'TLSA' => 'TLSA',
        ];
    }
}
