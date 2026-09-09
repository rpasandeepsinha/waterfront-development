<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Hosting\Requests;

// TODO implement correctly in WATER-5934

use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;

class HostingSsoRequest extends HostingProvisionRequest
{
    public ProvisionRequestName $name = ProvisionRequestName::GET_HOSTING_SSO;

    /**
     * @param string      $username       Provider username to generate SSO for
     * @param string|null $ipAddress      Only for Plesk Provider
     * @param bool        $redirectToMail Only for Plesk Provider
     */
    public function __construct(
        public readonly string $username,
        public protected(set) UuidInterface $context,
        public readonly ?string $ipAddress = null,
        public readonly bool $redirectToMail = false
    ) {
        $this->tag = $this->context;
    }
}
