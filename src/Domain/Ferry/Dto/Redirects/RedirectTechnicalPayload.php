<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Dto\Redirects;

readonly class RedirectTechnicalPayload
{
    public function __construct(
        public string $source,
        public string $destination,
        public string $type = '301'
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'destination' => $this->destination,
            'type' => $this->type,
        ];
    }
}
