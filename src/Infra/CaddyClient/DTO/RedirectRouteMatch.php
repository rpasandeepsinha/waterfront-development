<?php

declare(strict_types=1);

namespace Waterfront\Infra\CaddyClient\DTO;

class RedirectRouteMatch
{
    /**
     * @param list<string>|null                $host
     * @param list<string>|null                $path
     * @param array<string, list<string>>|null $query
     */
    public function __construct(
        public ?array $host = null,
        public ?array $path = null,
        public ?array $query = null,
    ) {
    }
}
