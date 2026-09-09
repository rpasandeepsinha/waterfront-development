<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Hosting\Requests;

// TODO implement correctly in WATER-5934

use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;

class HostingCreateRequest extends HostingProvisionRequest
{
    public ProvisionRequestName $name = ProvisionRequestName::CREATE_HOSTING;

    /**
     * @param string      $servicePlan      Service plan in Plesk or package name in DirectAdmin
     * @param string      $email            Customer email in both Plesk and DirectAdmin
     * @param string|null $domain           Will be generated if null
     * @param string|null $username         Will be generated if null
     * @param string|null $password         Will be generated if null
     * @param string|null $contactName      Only for Plesk provider
     * @param bool        $enableDns        Only for Directadmin provider
     * @param bool        $enableFtp        Only for Directadmin provider
     * @param bool        $enableSsh        Only for Directadmin provider
     * @param bool        $enableSsl        Only for Directadmin provider
     * @param bool|null   $installWordpress Only for Plesk provider
     * @param string|null $ipv4             Only for Plesk Provider
     */
    public function __construct(
        public readonly string $servicePlan,
        public readonly string $email,
        public protected(set) UuidInterface $context,
        public readonly ?string $domain = null,
        public readonly ?string $username = null,
        public readonly ?string $password = null,
        public readonly ?string $contactName = null,
        public readonly bool $enableDns = false,
        public readonly bool $enableFtp = false,
        public readonly bool $enableSsh = false,
        public readonly bool $enableSsl = false,
        public readonly ?bool $installWordpress = false,
        public readonly ?string $ipv4 = null
    ) {
        $this->tag = $this->context;
    }
}
