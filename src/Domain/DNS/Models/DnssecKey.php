<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Models;

use Waterfront\Infra\PowerDnsClient\Entities\PowerDnsSecKey;

class DnssecKey
{
    private readonly int $flags;

    private readonly int $algorithm;

    private readonly int $protocol;

    private readonly string $pubKey;

    public function __construct(PowerDnsSecKey $key)
    {
        $keyinfo = [];

        $matched = preg_match(
            '/^(?<flags>[0-9]+) (?<protocol>[0-9]+) (?<alg>[0-9]+) (?<pubKey>.*)$/',
            $key->getDnsKey() ?? '',
            $keyinfo,
        );

        $this->flags = $matched === 1 ? intval($keyinfo['flags']) : 0;
        $this->protocol = $matched === 1 ? intval($keyinfo['protocol']) : 0;
        $this->algorithm = $matched === 1 ? intval($keyinfo['alg']) : 0;
        $this->pubKey = $matched === 1 ? strval($keyinfo['pubKey']) : '';
    }

    /**
     * @return array{flags: int, alg: int, protocol: int, pubKey: string}
     */
    public function toArray(): array
    {
        return [
            'flags' => $this->getFlags(),
            'alg' => $this->getAlgorithm(),
            'protocol' => $this->getProtocol(),
            'pubKey' => $this->getPubKey(),
        ];
    }

    public function getFlags(): int
    {
        return $this->flags;
    }

    public function getAlgorithm(): int
    {
        return $this->algorithm;
    }

    public function getProtocol(): int
    {
        return $this->protocol;
    }

    public function getPubKey(): string
    {
        return $this->pubKey;
    }
}
