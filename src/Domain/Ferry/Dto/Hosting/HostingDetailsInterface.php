<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Dto\Hosting;

interface HostingDetailsInterface
{
    /**
     * @param array<string, string|int> $details
     */
    public static function fromArray(array $details): self;

    /**
     * @return array<string, string|int|null>
     */
    public function toArray(): array;

    public function getUsername(): string;
}
