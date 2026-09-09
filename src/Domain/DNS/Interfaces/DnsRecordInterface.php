<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Interfaces;

interface DnsRecordInterface
{
    public function getType(): string;

    public function getName(): string;

    public function getContent(): string;

    public function getTtl(): ?int;

    public function isDisabled(): bool;

    /**
     * @return array<mixed>
     */
    public function toArray(): array;
}
