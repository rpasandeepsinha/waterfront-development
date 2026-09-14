<?php

declare(strict_types=1);

namespace Waterfront\Domain\VPS\Repositories;

use Waterfront\Domain\VPS\Models\ManagerDomainDeployment;

class ManagerDomainDeploymentRepository
{
    public function getManagerDomainDeploymentByEnvironment(
        int $customerId,
        int $environmentId,
    ): ?ManagerDomainDeployment {
        return ManagerDomainDeployment::query()
            ->where('customer_id', $customerId)
            ->where('environment_id', $environmentId)
            ->first();
    }
}
