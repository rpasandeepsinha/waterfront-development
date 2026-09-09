<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Sitebuilder\Requests;

use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;

class GetBasekitUserByRefRequest extends SitebuilderProvisionRequestProvision
{
    public ProvisionRequestName $name = ProvisionRequestName::GET_BASEKIT_USER_BY_REF_REQUEST;

    public function __construct(
        public protected(set) UuidInterface $context,
        public int $userRef
    ) {
        $this->tag = $this->context;
    }
}
