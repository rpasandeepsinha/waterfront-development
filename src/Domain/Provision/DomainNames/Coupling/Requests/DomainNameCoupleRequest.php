<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\DomainNames\Coupling\Requests;

use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;

class DomainNameCoupleRequest extends DomainNameCoupleProvisionRequest
{
    public ProvisionRequestName $name = ProvisionRequestName::COUPLE_DOMAIN;

    /**
     * @param string        $domain      Domain to couple
     * @param UuidInterface $requestUuid UUID of the request that relates to the deployment that needs to be coupled.
     */
    public function __construct(
        public readonly string $domain,
        public readonly UuidInterface $requestUuid,
        public protected(set) UuidInterface $context
    ) {
        $this->tag = $this->context;
    }
}
