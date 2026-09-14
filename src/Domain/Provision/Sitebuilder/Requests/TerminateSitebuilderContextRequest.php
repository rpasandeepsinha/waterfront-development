<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Sitebuilder\Requests;

use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;

class TerminateSitebuilderContextRequest extends SitebuilderProvisionRequestProvision
{
    public ProvisionRequestName $name = ProvisionRequestName::TERMINATE_SITEBUILDER_CONTEXT;

    public function __construct(
        public protected(set) UuidInterface $context,
    ) {
        $this->tag = $this->context;
    }
}
