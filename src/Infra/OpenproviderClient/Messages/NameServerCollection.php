<?php

declare(strict_types=1);

namespace Waterfront\Infra\OpenproviderClient\Messages;

class NameServerCollection
{
    /** @var NameServer[] */
    private readonly array $nameServers;

    public function __construct(NameServer ...$nameServers)
    {
        $this->nameServers = $nameServers;
    }

    /** @return array<mixed> */
    public function toArray(): array
    {
        return [
            'array' => [
                'item' => array_map(fn (NameServer $nameServer): array => $nameServer->toArray(), $this->nameServers),
            ],
        ];
    }
}
