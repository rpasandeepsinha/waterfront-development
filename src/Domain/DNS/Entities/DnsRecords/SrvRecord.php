<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Entities\DnsRecords;

use Waterfront\Domain\DNS\Interfaces\ContentWithDotInterface;
use Waterfront\Domain\DNS\Interfaces\DnsRecordInterface;

class SrvRecord extends AbstractRecord implements DnsRecordInterface, ContentWithDotInterface
{
    public function __construct(
        string $name,
        string $content,
        private readonly int $priority,
        private readonly int $weight,
        private readonly int $port,
        int $ttl,
        bool $disabled = false
    ) {
        parent::__construct('SRV', $name, rtrim($content, '.'), $ttl, $disabled);
    }

    public function getContentPlusDot(): string
    {
        return $this->getContent() . '.';
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function getWeight(): int
    {
        return $this->weight;
    }

    public function getPort(): int
    {
        return $this->port;
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
            'priority' => $this->getPriority(),
            'weight' => $this->getWeight(),
            'port' => $this->getPort(),
        ];
    }
}
