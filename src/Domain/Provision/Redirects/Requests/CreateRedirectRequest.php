<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Redirects\Requests;

use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;
use Waterfront\Domain\Provision\Redirects\Enums\RedirectType;

class CreateRedirectRequest extends RedirectProvisionRequest
{
    public ProvisionRequestName $name = ProvisionRequestName::CREATE_REDIRECT;

    /**
     * @param string       $domain         The 'primary' domain for the provider to bind all redirects to.
     * @param string       $destinationUrl The default url the 'primary' domain should redirect to, we cant provision without it.
     * @param RedirectType $redirectType   The type of redirect, e.g. 301, 302, or frame.
     */
    public function __construct(
        public readonly string $domain,
        public readonly string $destinationUrl,
        public readonly RedirectType $redirectType,
        public protected(set) UuidInterface $context,
    ) {
        $this->tag = $this->context;
    }
}
