<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\WpToolkit\DTO;

readonly class WpInstanceInfo
{
    public function __construct(
        public int $id,
        public string $title,
        public string $url,
        public WpInstanceDomainInfo $domain,
    ) {
    }
}
