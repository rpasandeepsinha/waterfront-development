<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Repositories;

use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Provision\Models\ProvisionDeployment;
use Waterfront\Domain\Provision\Models\ProvisioningRequest;

class ProvisioningDeploymentRepository
{
    public function findDeploymentByRequestUuid(UuidInterface $requestUuid): ?ProvisionDeployment
    {
        return ProvisioningRequest::where('uuid', $requestUuid)->first()?->deployment;
    }
}
