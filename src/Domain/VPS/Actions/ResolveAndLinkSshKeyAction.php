<?php

declare(strict_types=1);

namespace Waterfront\Domain\VPS\Actions;

use Waterfront\Domain\VPS\Exceptions\SshKeyNotFoundException;
use Waterfront\Domain\VPS\Models\ManagerDomainDeployment;
use Waterfront\Domain\VPS\Models\SshKey;
use Waterfront\Domain\VPS\Repositories\SshKeyRepository;
use Waterfront\Domain\VPS\Services\CloudstackService;
use Webmozart\Assert\Assert;

class ResolveAndLinkSshKeyAction
{
    public function __construct(
        private readonly SshKeyRepository $sshKeyRepository,
        private readonly CloudstackService $cloudstackService
    ) {
    }

    public function execute(
        string $sshKeyUuid,
        int $vmDeploymentId,
        ManagerDomainDeployment $managerDomainDeployment
    ): string {
        $sshKey = $this->sshKeyRepository->findByUuid($sshKeyUuid);

        if (! $sshKey instanceof SshKey) {
            throw new SshKeyNotFoundException('Ssh key not found while trying to resolve the cloudstack name');
        }

        Assert::string($sshKey->cloudstack_ssh_name);

        if (! $this->sshKeyRepository->keyLinkedToManagerDomain($sshKey, $managerDomainDeployment->id)) {
            $this->cloudstackService->registerSshKeyPair(
                managerDomainDeployment: $managerDomainDeployment,
                name: $sshKey->cloudstack_ssh_name,
                publicKey: $sshKey->public_key
            );

            $sshKey->managerDomains()->sync([$managerDomainDeployment->id]);
        }

        $sshKey->virtualMachineDeployments()->sync([$vmDeploymentId]);
        return $sshKey->cloudstack_ssh_name;
    }
}
