<?php

declare(strict_types=1);

namespace Waterfront\Infra\CaddyClient\DTO;

use Symfony\Component\Serializer\Attribute\Ignore;

class RedirectRouteHandle
{
    #[Ignore]
    public ?string $location {
        get {
            $location = $this->headers['Location'] ?? null;

            return ($location === null || $location === [])
                ? null
                : $location[0];
        }
    }

    #[Ignore]
    public ?string $contentType {
        get {
            $contentType = $this->headers['Content-Type'] ?? null;

            return ($contentType === null || $contentType === [])
                ? null
                : $contentType[0];
        }
    }

    /**
     * @param array<string, list<string>>|null $headers
     */
    public function __construct(
        public string $handler,
        public ?int $statusCode = null,
        public ?array $headers = null,
        public ?string $body = null,
    ) {
    }
}
