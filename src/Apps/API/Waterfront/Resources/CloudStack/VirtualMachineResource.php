<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Resources\CloudStack;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Apps\API\Waterfront\Resources\SubscriptionResource;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\VPS\Models\ManagerDomainDeployment;
use Waterfront\Domain\VPS\Models\VirtualMachineDeployment;

class VirtualMachineResource extends JsonResource
{
    /**
     * @param Request $request
     *
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        /** @var Subscription $subscription */
        $subscription = $this->resource;

        /** @var ?VirtualMachineDeployment $virtualMachineDeployment */
        $virtualMachineDeployment = $subscription->cloudStackVirtualMachineDeployment;

        /** @var ?ManagerDomainDeployment $managerDomainDeployment */
        $managerDomainDeployment = $virtualMachineDeployment?->managerDomainDeployment;

        $resource = [
            'uuid' => $subscription->uuid,
            'cloudstack_id' => $virtualMachineDeployment->cloudstack_id ?? '',
            'subscription' => SubscriptionResource::make($subscription)->toArray($request),
            'technical_status' => $subscription->technical_status,
            'last_action_status' => $virtualMachineDeployment?->last_action_status?->value,
            'custom_name' => $virtualMachineDeployment->custom_name ?? '',
        ];

        // Include the IP address and vps status in the resource
        // when they're provided as additional data
        $ipAddress = $this->additional['ipAddress'] ?? null;
        $ip6Address = $this->additional['ip6Address'] ?? null;
        $vps_status = $this->additional['vps_status'] ?? null;
        $sshKey = $virtualMachineDeployment?->sshKeys()->first();

        if ($ipAddress !== null && $vps_status !== null && $ip6Address !== null) {
            $resource['ipAddress'] = $ipAddress;
            $resource['ip6Address'] = $ip6Address;
            $resource['vps_status'] = $vps_status;
            $resource['sshName'] = $sshKey?->key_name;
            $resource['sshKey'] = $sshKey?->uuid;
            $resource['environmentName'] = $managerDomainDeployment?->environment?->name;

            unset(
                $this->additional['ipAddress'],
                $this->additional['ip6Address'],
                $this->additional['vps_status'],
                $this->additional['sshName'],
                $this->additional['sshKey'],
                $this->additional['environmentName'],
            );
        }

        return $resource;
    }
}
