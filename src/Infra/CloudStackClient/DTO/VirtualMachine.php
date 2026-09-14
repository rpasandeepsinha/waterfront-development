<?php

declare(strict_types=1);

namespace Waterfront\Infra\CloudStackClient\DTO;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Waterfront\Infra\CloudStackClient\Enums\CloudstackMachineState;

class VirtualMachine
{
    /**
     * @param Nic[] $nic
     */
    public function __construct(
        public string $id,
        public string $name,
        #[SerializedName('domainid')]
        public string $domainId,
        public string $account,
        public string $username,
        public array $nic,
        public CloudstackMachineState $state,
        #[SerializedName('serviceofferingid')]
        public string $serviceOfferingId,
        public ?string $password = null,
    ) {
    }
}
