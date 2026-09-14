<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Microsoft365\Requests;

use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;

class Microsoft365AuthorizationUrlRequest extends Microsoft365ProvisionRequest
{
    public ProvisionRequestName $name = ProvisionRequestName::GET_MICROSOFT365_AUTHORIZATION_URL;

    public ?ProvisionProvider $provider = ProvisionProvider::MICROSOFT_ONLINE;

    /**
     * @param string $tenantName Name of the tenant to get an authorization URL from, should exist on microsoft.com
     */
    public function __construct(
        public readonly string $tenantName,
        public protected(set) UuidInterface $context,
    ) {
        $this->tag = $this->context;
    }
}
