<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Entities\DnsRecords;

use Waterfront\Domain\DNS\Interfaces\ContentWithDotInterface;
use Waterfront\Domain\DNS\Interfaces\DnsRecordInterface;

class MxRecord extends AbstractRecord implements DnsRecordInterface, ContentWithDotInterface
{
    public function __construct(
        string $name,
        string $content,
        private readonly int $priority,
        int $ttl,
        bool $disabled = false,
    ) {
        parent::__construct('MX', $name, rtrim($content, '.'), $ttl, $disabled);
    }

    public function getNamePlusDot(): string
    {
        return $this->getName() . '.';
    }

    public function getContentPlusDot(): string
    {
        return $this->getContent() . '.';
    }

    public function getPriority(): int
    {
        return $this->priority;
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
        ];
    }
}
