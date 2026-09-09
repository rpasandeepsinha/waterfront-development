<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Sitebuilder\Requests;

use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;

class RollbackBasekitDeploymentsFromMigrationRequest extends SitebuilderProvisionRequestProvision
{
    public ProvisionRequestName $name = ProvisionRequestName::ROLLBACK_BASEKIT_DEPLOYMENTS_FROM_MIGRATION;

    public function __construct(
        public protected(set) UuidInterface $context,
        public UuidInterface $tagUuid,
    ) {
        $this->tag = $this->tagUuid;
    }
}
