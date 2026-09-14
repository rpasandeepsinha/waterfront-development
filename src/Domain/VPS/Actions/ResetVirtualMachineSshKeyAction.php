<?php

declare(strict_types=1);

namespace Waterfront\Domain\VPS\Actions;

use Psr\Log\LoggerInterface;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\VPS\Exceptions\ClientFactoryException;
use Waterfront\Domain\VPS\Exceptions\CloudstackNotFoundException;
use Waterfront\Domain\VPS\Exceptions\VirtualMachineNotFoundException;
use Waterfront\Domain\VPS\Interfaces\ClientFactoryInterface;
use Waterfront\Domain\VPS\Models\SshKey;
use Waterfront\Domain\VPS\Models\VirtualMachineDeployment;
use Waterfront\Domain\VPS\Repositories\SshKeyRepository;
use Waterfront\Domain\VPS\Services\VirtualMachineService;
use Waterfront\Infra\CloudStackClient\Enums\CloudstackMachineState;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

class ResetVirtualMachineSshKeyAction
{
    public function __construct(
        private readonly VirtualMachineService $virtualMachineService,
        private readonly SshKeyRepository $sshKeyRepository,
        private readonly ClientFactoryInterface $cloudStackClientFactory,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @throws VirtualMachineNotFoundException
     * @throws ClientFactoryException
     */
    public function execute(VirtualMachineDeployment $virtualMachineDeployment, SshKey $newSshKey): bool
    {
        $virtualMachineCurrentSshKey = $virtualMachineDeployment->sshKeys()->first();

        if ($virtualMachineCurrentSshKey === null) {
            $this->logger->error('Cant reset virtual Machine SSH key | There is no key set yet on the virtual machine', [
                LoggingContextKeys::CUSTOMER_ID => $virtualMachineDeployment->subscription->customer_id,
                LoggingContextKeys::SUBSCRIPTION_UUID => $virtualMachineDeployment->subscription_uuid,
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::VPS->value,
                LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CLOUDSTACK->value,
                LoggingContextKeys::META => [
                    'new_ssh_key_id' => $newSshKey->id,
                ],
            ]);

            return false;
        }

        if (! $this->isVirtualMachineInStoppedState($virtualMachineDeployment)) {
            $this->logger->error('Cant reset virtual Machine SSH key | The machine is not in a stopped state', [
                LoggingContextKeys::CUSTOMER_ID => $virtualMachineDeployment->subscription->customer_id,
                LoggingContextKeys::SUBSCRIPTION_UUID => $virtualMachineDeployment->subscription_uuid,
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::VPS->value,
                LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CLOUDSTACK->value,
                LoggingContextKeys::META => [
                    'new_ssh_key_id' => $newSshKey->id,
                ],
            ]);

            return false;
        }

        $isNewKeyLinkedToEnvironment = $this->sshKeyRepository->keyLinkedToManagerDomain(
            sshKey: $newSshKey,
            domainId: $virtualMachineDeployment->managerDomainDeployment->id,
        );

        if (! $isNewKeyLinkedToEnvironment) {
            $this->registerNewKeyInCloudstack($virtualMachineDeployment, $newSshKey);
            $newSshKey->managerDomains()->attach($virtualMachineDeployment->managerDomainDeployment);
        }

        $newSshKey->virtualMachineDeployments()->attach($virtualMachineDeployment);
        $virtualMachineCurrentSshKey->virtualMachineDeployments()->detach($virtualMachineDeployment);

        $logMessage = sprintf(
            'Resetting SSH key for virtual machine with subscription uuid : %s',
            $virtualMachineDeployment->subscription_uuid,
        );
        $this->logger->info($logMessage, [
            LoggingContextKeys::CUSTOMER_ID => $virtualMachineDeployment->subscription->customer_id,
            LoggingContextKeys::SUBSCRIPTION_UUID => $virtualMachineDeployment->subscription_uuid,
            LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::VPS->value,
            LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CLOUDSTACK->value,
            LoggingContextKeys::META => [
                'old_ssh_key_id' => $virtualMachineCurrentSshKey->id,
                'old_ssh_key_cloudstack_name' => $virtualMachineCurrentSshKey->cloudstack_ssh_name,
                'new_ssh_key_id' => $newSshKey->id,
                'new_ssh_key_cloudstack_name' => $newSshKey->cloudstack_ssh_name,
            ],
        ]);

        Assert::string($newSshKey->cloudstack_ssh_name);

        return $this->virtualMachineService->resetSshKey(
            deployment: $virtualMachineDeployment,
            newKeyName: $newSshKey->cloudstack_ssh_name,
        );
    }

    private function isVirtualMachineInStoppedState(VirtualMachineDeployment $virtualMachineDeployment): bool
    {
        try {
            $virtualMachine = $this->virtualMachineService->findByDeployment($virtualMachineDeployment);
        } catch (CloudstackNotFoundException $cloudstackNotFoundException) {
            throw new VirtualMachineNotFoundException(
                message: $cloudstackNotFoundException->getMessage(),
                previous: $cloudstackNotFoundException,
            );
        }

        if ($virtualMachine === null) {
            throw new VirtualMachineNotFoundException(sprintf(
                'Virtual Machine for subscription with uuid %s not found ',
                $virtualMachineDeployment->subscription_uuid,
            ));
        }

        return $virtualMachine->state === CloudstackMachineState::STOPPED;
    }

    /**
     * @throws ClientFactoryException
     */
    private function registerNewKeyInCloudstack(
        VirtualMachineDeployment $virtualMachineDeployment,
        SshKey $sshKey,
    ): void {
        Assert::string($sshKey->cloudstack_ssh_name);

        $client = $this->cloudStackClientFactory->create($virtualMachineDeployment->managerDomainDeployment);
        $client->registerSshKeyPair(
            name: $sshKey->cloudstack_ssh_name,
            publicKey: $sshKey->public_key,
        );
    }
}
