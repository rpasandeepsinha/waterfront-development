<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Events;

class CreateDns
{
    public function __construct(
        public readonly string $subscriptionUuid,
        public readonly string $domain,
    ) {
    }
}
