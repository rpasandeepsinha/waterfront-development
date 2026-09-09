<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Sitebuilder\Requests;

use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;

class TerminateSitebuilderRequest extends SitebuilderProvisionRequestProvision
{
    public ProvisionRequestName $name = ProvisionRequestName::TERMINATE_SITEBUILDER_SITE;

    public function __construct(
        public protected(set) UuidInterface $context,
        public readonly UuidInterface $tagUuid,
    ) {
        $this->tag = $this->tagUuid;
    }
}
