<?php

declare(strict_types=1);

namespace Waterfront\Domain\VPS\Interfaces;

use Illuminate\Support\Collection;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\VPS\Models\ManagerDomainDeployment;
use Waterfront\Domain\VPS\Models\VirtualMachineDeployment;
use Waterfront\Infra\CloudStackClient\DTO\VirtualMachine;

interface VirtualMachineServiceInterface
{
    public function findByDeployment(VirtualMachineDeployment $deployment): ?VirtualMachine;

    /**
     * @return VirtualMachine[]
     */
    public function findVirtualMachines(ManagerDomainDeployment $mdDeployment): array;

    public function start(VirtualMachineDeployment $deployment): bool;

    public function stop(VirtualMachineDeployment $deployment): bool;

    public function reboot(VirtualMachineDeployment $deployment): bool;

    /**
     * @return Collection<int, Product>
     */
    public function getAvailableReinstallOptions(Subscription $osSubscription): Collection;

    public function reinstall(VirtualMachineDeployment $deployment, Product $newOs, ?string $sshKeyUuid = null): bool;

    public function destroy(VirtualMachineDeployment $vmDeployment, ?string $sshKeyUuid = null): bool;

    public function handleByStateAndDeployment(string $state, VirtualMachineDeployment $deployment): bool;

    public function resetPassword(VirtualMachineDeployment $deployment, string $password): bool;

    public function resetSshKey(VirtualMachineDeployment $deployment, string $newKeyName): bool;

    public function getConsole(VirtualMachineDeployment $deployment): string;
}
