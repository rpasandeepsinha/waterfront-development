<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Entities;

class VpsDnsRecords
{
    /**
     * @return array<array<string, string|int>>
     */
    public static function getRecords(): array
    {
        return [
            [
                'name' => '{domain}',
                'type' => 'A',
                'ttl' => 600,
                'content' => '127.0.0.1',
            ],
            [
                'name' => '{domain}',
                'type' => 'AAAA',
                'ttl' => 600,
                'content' => '0:0:0:0:0:0:0:1',
            ],
        ];
    }
}
