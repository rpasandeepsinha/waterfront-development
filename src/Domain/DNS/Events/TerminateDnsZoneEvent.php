<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Events;

class TerminateDnsZoneEvent
{
    public function __construct(
        public readonly string $subscriptionUuid,
        public readonly string $domain,
        public readonly string $dnsProductUuid
    ) {
    }
}
