<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\WpToolkit\DTO;

readonly class WpInstanceDomainInfo
{
    public function __construct(
        public string $name,
    ) {
    }
}
