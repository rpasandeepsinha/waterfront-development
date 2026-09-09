<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Dto\Hosting;

readonly class MappableHostingPayload
{
    /**
     * @param array<string, int|string> $serverData
     */
    public function __construct(
        public string $referenceSubscriptionId,
        public string $driver,
        public string $hostname,
        public array $serverData,
    ) {
    }
}
