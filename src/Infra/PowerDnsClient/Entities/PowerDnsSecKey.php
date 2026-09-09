<?php

declare(strict_types=1);

namespace Waterfront\Infra\PowerDnsClient\Entities;

use JsonException;
use Webmozart\Assert\Assert;

/**
 * This domain class is being used to serialize a DNSSEC key.
 * Do not change fields unless you are sure.
 */
class PowerDnsSecKey
{
    private ?int $id = null;

    private ?string $type = null;

    private ?string $keytype = null;

    private ?bool $active = null;

    private ?bool $published = null;

    private ?string $dnskey = null;

    /**
     * @var string[]
     */
    private array $ds = [];

    private ?string $privatekey = null;

    private ?string $algorithm = null;

    private ?int $bits = null;

    /**
     * @param array<mixed> $key
     */
    public static function fromArray(array $key): PowerDnsSecKey
    {
        $obj = new self();
        if (array_key_exists('id', $key)) {
            Assert::nullOrInteger($key['id']);
            $obj->id = $key['id'] ?? null;
        }

        $obj->type = $key['type'] ?? null;
        $obj->keytype = $key['keytype'] ?? null;
        $obj->active = $key['active'] ?? null;
        $obj->published = $key['published'] ?? null;
        $obj->dnskey = $key['dnskey'] ?? null;
        $obj->ds = $key['ds'] ?? [];
        $obj->privatekey = $key['privatekey'] ?? null;
        $obj->algorithm = $key['algorithm'] ?? null;
        $obj->bits = $key['bits'] ?? null;

        return $obj;
    }

    /**
     * @throws JsonException
     */
    public static function fromString(string $key): PowerDnsSecKey
    {
        $decoded = json_decode($key, true, 512, JSON_THROW_ON_ERROR);
        assert(is_array($decoded));

        return self::fromArray($decoded);
    }

    /**
     * @return array{
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
     *   }
     */
    public function toArray(): array
    {
        return [
            'id'         => $this->getId(),
            'type'       => $this->getType(),
            'keytype'    => $this->getKeyType(),
            'active'     => $this->isActive(),
            'published'  => $this->isPublished(),
            'dnskey'     => $this->getDnsKey(),
            'ds'         => $this->getDs(),
            'privatekey' => $this->getPrivatekey(),
            'algorithm'  => $this->getAlgorithm(),
            'bits'       => $this->getBits(),
        ];
    }

    /**
     * @throws JsonException
     */
    public function toString(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    public function isType(string $type): bool
    {
        return $this->getKeyType() === $type;
    }

    public function isAlgorithm(string $algorithm): bool
    {
        return $this->getAlgorithm() === $algorithm;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function getKeyType(): ?string
    {
        return $this->keytype;
    }

    public function isActive(): ?bool
    {
        return $this->active;
    }

    public function isPublished(): ?bool
    {
        return $this->published;
    }

    public function getDnsKey(): ?string
    {
        return $this->dnskey;
    }

    /**
     * @return string[]
     */
    public function getDs(): array
    {
        return $this->ds;
    }

    public function getPrivatekey(): ?string
    {
        return $this->privatekey;
    }

    public function getAlgorithm(): ?string
    {
        return $this->algorithm;
    }

    public function getBits(): ?int
    {
        return $this->bits;
    }
}
