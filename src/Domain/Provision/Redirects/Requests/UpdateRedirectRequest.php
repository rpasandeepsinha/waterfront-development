<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Redirects\Requests;

use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;
use Waterfront\Domain\Provision\Redirects\Enums\RedirectType;

class UpdateRedirectRequest extends RedirectProvisionRequest
{
    public ProvisionRequestName $name = ProvisionRequestName::UPDATE_REDIRECT;

    public function __construct(
        public readonly string $oldSource,
        public readonly string $newSource,
        public readonly string $destinationUrl,
        public readonly RedirectType $redirectType,
        public protected(set) UuidInterface $context,
    ) {
        $this->tag = $this->context;
    }
}
