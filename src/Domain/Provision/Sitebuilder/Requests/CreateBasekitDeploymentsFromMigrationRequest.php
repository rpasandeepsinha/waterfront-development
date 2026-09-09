<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Sitebuilder\Requests;

use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;

class CreateBasekitDeploymentsFromMigrationRequest extends SitebuilderProvisionRequestProvision
{
    public ProvisionRequestName $name = ProvisionRequestName::CREATE_BASEKIT_DEPLOYMENTS_FROM_MIGRATION;

    public function __construct(
        public readonly string $domain,
        public readonly int $userRef,
        public readonly int $siteRef,
        public protected(set) UuidInterface $context,
    ) {
        $this->tag = $this->context;
    }
}
