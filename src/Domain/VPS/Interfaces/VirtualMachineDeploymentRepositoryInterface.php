<?php

declare(strict_types=1);

namespace Waterfront\Domain\VPS\Interfaces;

use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\VPS\Exceptions\VirtualMachineNotFoundException;
use Waterfront\Domain\VPS\Models\ManagerDomainDeployment;
use Waterfront\Domain\VPS\Models\VirtualMachineDeployment;

interface VirtualMachineDeploymentRepositoryInterface
{
    /**
     * Find a virtual machine deployment by its subscription UUID
     * and the customer ID it belongs to.
     *
     * @throws VirtualMachineNotFoundException
     */
    public function findBySubscriptionUuid(string $uuid, int $customerId): VirtualMachineDeployment;

    public function firstOrCreateBySubscriptionUuid(string $subscriptionUuid, ManagerDomainDeployment $managerDomainDeployment): VirtualMachineDeployment;

    /**
     * @throws VirtualMachineNotFoundException
     */
    public function getOsSubscriptionChildFromSubscriptionUuid(string $subscriptionUuid): Subscription;

    public function updateCustomName(VirtualMachineDeployment $virtualMachineDeployment, string $customName): bool;
}
