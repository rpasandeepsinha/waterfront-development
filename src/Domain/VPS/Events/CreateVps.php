<?php

declare(strict_types=1);

namespace Waterfront\Domain\VPS\Events;

class CreateVps
{
    /**
     * @param ?non-empty-string $sshKeyUuid
     */
    public function __construct(
        public readonly string $subscriptionUuid,
        public readonly ?string $sshKeyUuid,
    ) {
    }
}
