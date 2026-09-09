<?php

declare(strict_types=1);

namespace Waterfront\Domain\Domains\Events;

use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class CreateDomain
{
    public function __construct(
        public readonly string $domain,
        public readonly Subscription $subscription,
        public readonly DomainDeployment $domainDeployment,
    ) {
    }

    public function getTransferSecret(): ?string
    {
        return $this->domainDeployment->transfer_secret;
    }
}
