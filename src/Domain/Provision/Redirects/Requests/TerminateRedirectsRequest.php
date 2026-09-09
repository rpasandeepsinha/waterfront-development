<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Redirects\Requests;

use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;

class TerminateRedirectsRequest extends RedirectProvisionRequest
{
    public ProvisionRequestName $name = ProvisionRequestName::TERMINATE_REDIRECTS;

    public function __construct(
        public protected(set) UuidInterface $context
    ) {
        $this->tag = $this->context;
    }
}
