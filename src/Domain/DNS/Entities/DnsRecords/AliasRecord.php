<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Entities\DnsRecords;

use Waterfront\Domain\DNS\Interfaces\ContentWithDotInterface;
use Waterfront\Domain\DNS\Interfaces\DnsRecordInterface;

class AliasRecord extends AbstractRecord implements DnsRecordInterface, ContentWithDotInterface
{
    public function __construct(string $name, string $content, int $ttl, bool $disabled = false)
    {
        parent::__construct('ALIAS', $name, rtrim($content, '.'), $ttl, $disabled);
    }

    public function getNamePlusDot(): string
    {
        return $this->getName() . '.';
    }

    public function getContentPlusDot(): string
    {
        return $this->getContent() . '.';
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
