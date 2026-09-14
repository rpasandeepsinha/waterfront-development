<?php

declare(strict_types=1);

namespace Waterfront\Domain\VPS\Actions;

use Psr\Log\LoggerInterface;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\VPS\Exceptions\CloudstackException;
use Waterfront\Domain\VPS\Exceptions\SshKeyNotDeletableException;
use Waterfront\Domain\VPS\Models\ManagerDomainDeployment;
use Waterfront\Domain\VPS\Models\SshKey;
use Waterfront\Domain\VPS\Repositories\SshKeyRepository;
use Waterfront\Domain\VPS\Services\CloudstackService;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

class DeleteSshKeyAction
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly CloudstackService $cloudstackService,
        private readonly SshKeyRepository $sshKeyRepository,
    ) {
    }

    /**
     * @throws SshKeyNotDeletableException
     */
    public function execute(SshKey $sshKey, Customer $customer): bool
    {
        if (! $this->isDeletable($sshKey)) {
            $this->logger->debug('SSH key is not deletable', [
                LoggingContextKeys::CUSTOMER_ID => $customer->id,
                LoggingContextKeys::META => [
                    'key_id' => $sshKey->id,
                    'key_name' => $sshKey->key_name,
                    'fingerprint' => $sshKey->fingerprint,
                    'ssh_key_name' => $sshKey->cloudstack_ssh_name,
                ],
            ]);

            throw new SshKeyNotDeletableException(
                'The SSH key cannot be removed because it is still linked to a virtual machine.',
            );
        }

        $withErrors = false;
        $sshKey->managerDomains->each(function (ManagerDomainDeployment $managerDomainDeployment) use (
            $sshKey,
            $customer,
            &$withErrors,
        ): void {
            if ($this->deleteSshKeyFromManagerDomain(
                sshKey: $sshKey,
                managerDomainDeployment: $managerDomainDeployment,
                customerId: $customer->id,
            )) {
                $managerDomainDeployment->sshKeys()->detach($sshKey->id);

                return;
            }

            $withErrors = true;
        });

        return ! $withErrors && $this->sshKeyRepository->deleteSshKey($sshKey);
    }

    private function deleteSshKeyFromManagerDomain(
        SshKey $sshKey,
        ManagerDomainDeployment $managerDomainDeployment,
        int $customerId,
    ): bool {
        Assert::string($sshKey->cloudstack_ssh_name);

        try {
            $cloudstackResponse = $this->cloudstackService->deleteSshKeyPair(
                $managerDomainDeployment,
                $sshKey->cloudstack_ssh_name,
            );

            if ($cloudstackResponse->success) {
                $this->logger->info('SSH key deleted successfully in CloudStack.', [
                    LoggingContextKeys::CUSTOMER_ID => $customerId,
                    LoggingContextKeys::META => [
                        'cloudstack_message' => $cloudstackResponse->displaytext,
                        'key_name' => $sshKey->key_name,
                        'fingerprint' => $sshKey->fingerprint,
                        'ssh_key_name' => $sshKey->cloudstack_ssh_name,
                    ],
                ]);

                return true;
            }

            $this->logger->error('SSH key could not be deleted due to an error in cloudstack', [
                LoggingContextKeys::CUSTOMER_ID => $customerId,
                LoggingContextKeys::META => [
                    'cloudstack_error_message' => $cloudstackResponse->displaytext,
                    'key_name' => $sshKey->key_name,
                    'fingerprint' => $sshKey->fingerprint,
                    'ssh_key_name' => $sshKey->cloudstack_ssh_name,
                    'manager_domain_deployment_id' => $managerDomainDeployment->id,
                ],
            ]);

            return false;
        } catch (CloudstackException $exception) {
            $this->logger->error('Cloudstack responded with a client error: SSH key is not deleted', [
                LoggingContextKeys::CUSTOMER_ID => $customerId,
                LoggingContextKeys::EXCEPTION => $exception,
                LoggingContextKeys::META => [
                    'key_name' => $sshKey->key_name,
                    'fingerprint' => $sshKey->fingerprint,
                    'ssh_key_name' => $sshKey->cloudstack_ssh_name,
                    'manager_domain_deployment_id' => $managerDomainDeployment->id,
                    'environment_id' => $managerDomainDeployment->environment_id,
                ],
            ]);

            return false;
        }
    }

    private function isDeletable(SshKey $sshKey): bool
    {
        return $sshKey->virtualMachineDeployments()->count() === 0;
    }
}
