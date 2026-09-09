<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Entities;

class DefaultDnsRecords
{
    /**
     * @return array<array<string, string|int>>
     */
    public static function getRecords(): array
    {
        return [
            [
                'name'    => 'localhost.{domain}',
                'type'    => 'A',
                'ttl'     => 600,
                'content' => '127.0.0.1',
            ],
            [
                'name'    => '{domain}',
                'type'    => 'NS',
                'ttl'     => 3600,
                'content' => '{ns1}.',
            ],
            [
                'name'    => '{domain}',
                'type'    => 'NS',
                'ttl'     => 3600,
                'content' => '{ns2}.',
            ],
            [
                'name'    => '{domain}',
                'type'    => 'NS',
                'ttl'     => 3600,
                'content' => '{ns3}.',
            ],
            [
                'name'    => '{domain}',
                'type'    => 'SOA',
                'ttl'     => 3600,
                'content' => '{ns1}. domain-admin.2is.nl. 1539941638 3600 600 86400 3600',
            ],
        ];
    }
}
