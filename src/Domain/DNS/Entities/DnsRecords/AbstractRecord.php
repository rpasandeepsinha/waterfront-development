<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Entities\DnsRecords;

use Waterfront\Domain\DNS\Interfaces\DnsRecordInterface;

abstract class AbstractRecord implements DnsRecordInterface
{
    private readonly string $name;

    public function __construct(
        private readonly string $type,
        string $name,
        private readonly string $content,
        private readonly int $ttl,
        private readonly bool $disabled = false,
    ) {
        $this->name = rtrim($name, '.');
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getNamePlusDot(): string
    {
        return $this->getName() . '.';
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getTtl(): int
    {
        return $this->ttl;
    }

    public function isDisabled(): bool
    {
        return $this->disabled;
    }

    /**
     * @return array<mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->getName(),
            'content' => $this->getContent(),
            'type' => $this->getType(),
            'ttl' => $this->getTtl(),
            'disabled' => $this->isDisabled(),
        ];
    }
}
