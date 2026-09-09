<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Redirects\Requests;

use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;

class DeleteRedirectRequest extends RedirectProvisionRequest
{
    public ProvisionRequestName $name = ProvisionRequestName::DELETE_REDIRECT;

    public function __construct(
        public string $domainName,
        public protected(set) UuidInterface $context
    ) {
        $this->tag = $this->context;
    }
}
