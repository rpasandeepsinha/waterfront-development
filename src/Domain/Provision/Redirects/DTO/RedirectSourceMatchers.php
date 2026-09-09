<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Redirects\DTO;

readonly class RedirectSourceMatchers
{
    /**
     * @param list<string>|null                $paths
     * @param array<string, list<string>>|null $query
     */
    public function __construct(
        public string $host,
        public ?array $paths = null,
        public ?array $query = null,
    ) {
    }
}
