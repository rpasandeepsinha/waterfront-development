<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Dto\Redirects;

use Waterfront\Domain\Subscriptions\Models\Subscription;

readonly class RedirectMigrationPayload
{
    public function __construct(
        public Subscription $subscription,
        public RedirectTechnicalPayload $redirectTechnicalPayload,
    ) {
    }
}
