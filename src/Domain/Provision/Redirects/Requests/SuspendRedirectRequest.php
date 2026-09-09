<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Redirects\Requests;

use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;
use Waterfront\Domain\Provision\Interfaces\ProvisionContextRequestInterface;

class SuspendRedirectRequest extends RedirectProvisionRequest implements ProvisionContextRequestInterface
{
    public ProvisionRequestName $name = ProvisionRequestName::SUSPEND_REDIRECT;

    public function __construct(
        public protected(set) UuidInterface $context,
    ) {
        $this->tag = $this->context;
    }
}
