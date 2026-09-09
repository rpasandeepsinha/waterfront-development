<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Sitebuilder\Requests;

use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;

class UpdateSitebuilderRequest extends SitebuilderProvisionRequestProvision
{
    public ProvisionRequestName $name = ProvisionRequestName::UPDATE_SITEBUILDER;

    /**
     * @param int[] $packages
     */
    public function __construct(
        public UuidInterface $tagUuid,
        public protected(set) UuidInterface $context,
        public array $packages,
        public readonly int $contractPeriod,
    ) {
        $this->tag = $this->tagUuid;
    }
}
