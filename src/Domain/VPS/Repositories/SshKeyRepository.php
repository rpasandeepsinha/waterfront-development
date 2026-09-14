<?php

declare(strict_types=1);

namespace Waterfront\Domain\VPS\Repositories;

use Illuminate\Support\Collection;
use Ramsey\Uuid\Uuid;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\VPS\Models\SshKey;

class SshKeyRepository
{
    public function saveSshKey(Customer $customer, string $keyName, string $publicKey, string $fingerprint): SshKey
    {
        $sshKey = new SshKey();
        $sshKey->uuid = Uuid::uuid4();
        $sshKey->customer_id = $customer->id;
        $sshKey->key_name = $keyName;
        $sshKey->public_key = $publicKey;
        $sshKey->fingerprint = $fingerprint;
        $sshKey->cloudstack_ssh_name = sha1($publicKey);
        $sshKey->save();

        return $sshKey;
    }

    /**
     * @return Collection<int,SshKey>
     */
    public function findAllByCustomer(Customer $customer): Collection
    {
        return SshKey::where('customer_id', $customer->id)->get();
    }

    public function findByUuid(string $uuid): ?SshKey
    {
        return SshKey::where('uuid', $uuid)->first();
    }

    public function deleteSshKey(SshKey $sshKey): bool
    {
        $sshKey->refresh();
        if ($sshKey->managerDomains->isEmpty() && $sshKey->virtualMachineDeployments->isEmpty()) {
            return $sshKey->delete() ?? false;
        }

        return false;
    }

    public function keyExists(int $customer_id, string $fingerprint): bool
    {
        return SshKey::where('customer_id', $customer_id)->where('fingerprint', $fingerprint)->count() > 0;
    }

    public function keyLinkedToManagerDomain(SshKey $sshKey, int $domainId): bool
    {
        return $sshKey->managerDomains()->where('manager_domain_deployment_id', $domainId)->exists();
    }

    public function findByCustomerAndUuid(Customer $customer, string $uuid): SshKey
    {
        return SshKey::where('customer_id', $customer->id)->where('uuid', $uuid)->firstOrFail();
    }
}
