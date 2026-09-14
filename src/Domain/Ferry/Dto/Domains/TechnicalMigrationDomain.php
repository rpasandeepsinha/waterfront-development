<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Dto\Domains;

use Waterfront\Domain\Domains\DTO\DomainDetailsDTO;
use Waterfront\Domain\Subscriptions\Models\Subscription;

readonly class TechnicalMigrationDomain
{
    public function __construct(
        public Subscription $subscription,
        public DomainDetailsDTO $domainDetails,
    ) {
    }
}
