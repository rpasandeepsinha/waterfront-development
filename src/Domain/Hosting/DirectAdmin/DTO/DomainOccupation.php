<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\DirectAdmin\DTO;

use Waterfront\Domain\Hosting\Models\HostingDeployment;

class DomainOccupation
{
    /**
     * @param string[] $domains
     */
    public function __construct(
        public HostingDeployment $hostingSubscription,
        public array $domains,
        public int $domainsInUse,
        public int $domainsAvailable,
        public int $maxDomains,
    ) {
    }
}
