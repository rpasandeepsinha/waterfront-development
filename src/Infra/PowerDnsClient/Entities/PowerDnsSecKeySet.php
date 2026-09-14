<?php

declare(strict_types=1);

namespace Waterfront\Infra\PowerDnsClient\Entities;

use InvalidArgumentException;
use JsonException;
use RuntimeException;

class PowerDnsSecKeySet
{
    /**
     * @var PowerDnsSecKey[]
     */
    private array $keys = [];

    public function findByType(
        string $type,
        string $algorithm = 'ECDSAP256SHA256',
    ): PowerDnsSecKey {
        $keys = $this->getKeys();

        foreach ($keys as $key) {
            // Normally there should only be a single of
            // the given type key under the zone.
            // In case of multiple csk keys just return the first match.
            if ($key->isType($type) && $key->isAlgorithm($algorithm)) {
                return $key;
            }
        }

        // Fallback search for ksk in case this is an old existing zone
        foreach ($keys as $key) {
            if ($key->isType('ksk') && $key->isAlgorithm('RSASHA256')) {
                return $key;
            }
        }

        // When looking for keys They should always exist.
        throw new RuntimeException(
            sprintf('Unable to acquire DNSSEC key for given type: %s', $type),
        );
    }

    public function addKey(PowerDnsSecKey $key): void
    {
        $this->keys[] = $key;
    }

    /**
     * @return PowerDnsSecKey[]
     */
    public function getKeys(): array
    {
        return $this->keys;
    }

    public static function fromString(string $keys): PowerDnsSecKeySet
    {
        $keysArray = json_decode($keys, true);
        assert(is_array($keysArray));

        if (json_last_error() !== JSON_ERROR_NONE) {
            $error = json_last_error();
            throw new InvalidArgumentException(
                sprintf('Unable to parse string: %s error: %s', $keys, $error),
            );
        }

        return self::fromArray($keysArray);
    }

    /**
     * @param array<array<mixed,mixed>> $keys
     */
    public static function fromArray(array $keys): PowerDnsSecKeySet
    {
        $set = new self();

        foreach ($keys as $key) {
            $set->addKey(PowerDnsSecKey::fromArray($key));
        }

        return $set;
    }

    /**
     * @throws JsonException
     */
    public function toString(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<int,array{
     *       id: ?int,
     *       type: ?string,
     *       keytype: ?string,
     *       active: ?bool,
     *       published: ?bool,
     *       dnskey: ?string,
     *       ds: array<string>,
     *       privatekey: ?string,
     *       algorithm: ?string,
     *       bits: ?int,
     *   }>
     */
    public function toArray(): array
    {
        $keys = [];

        foreach ($this->getKeys() as $key) {
            $keys[] = $key->toArray();
        }

        return $keys;
    }

    public function isEmpty(): bool
    {
        return $this->getKeys() === [];
    }
}
