<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Resources\CloudStack;

use JsonException;
use Waterfront\Apps\API\Waterfront\Resources\BaseTechnicalDeploymentResource;
use Waterfront\Domain\VPS\Models\VirtualMachineDeployment;

class VirtualMachineTechnicalDeploymentResource
{
    public function __construct(
        private readonly BaseTechnicalDeploymentResource $baseTechnicalDeploymentResource,
    ) {
    }

    /**
     * @return array <string, mixed>
     */
    public function toArray(
        VirtualMachineDeployment $deployment,
        string $ipv4Adress,
        string $ipv6Adress,
        string $vpsStatus,
    ): array {
        $sshKey = $deployment->sshKeys()->first();

        return [
            ...$this->baseTechnicalDeploymentResource->toArray($deployment),
            'cloudstack_id' => $deployment->cloudstack_id ?? '',
            'environmentName' => $deployment->managerDomainDeployment->environment->name ?? '',
            'ipAddress' => $ipv4Adress,
            'ip6Address' => $ipv6Adress,
            'vps_status' => $vpsStatus,
            'last_action_status' => $deployment->last_action_status?->value,
            'sshKey' => $sshKey?->uuid,
            'sshName' => $sshKey?->key_name,
            'custom_name' => $deployment->custom_name,
        ];
    }

    /**
     * @throws JsonException
     */
    public function toJson(
        VirtualMachineDeployment $deployment,
        string $ipv4Adress,
        string $ipv6Adress,
        string $vpsStatus,
    ): string {
        return json_encode(
            $this->toArray($deployment, $ipv4Adress, $ipv6Adress, $vpsStatus),
            flags: JSON_THROW_ON_ERROR,
        );
    }
}
