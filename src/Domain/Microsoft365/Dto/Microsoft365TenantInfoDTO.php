<?php

declare(strict_types=1);

namespace Waterfront\Domain\Microsoft365\Dto;

use Waterfront\Domain\Microsoft365\Enums\PrimaryDomainStatus;

class Microsoft365TenantInfoDTO
{
    /**
     * @param Microsoft365DeploymentDTO[] $deployments
     * @param string[]                    $availableActions
     */
    public function __construct(
        public ?string $coupledDomainSubscriptionUuid,
        public ?string $tenantId,
        public string $tenantName,
        public string $primaryDomain,
        public ?PrimaryDomainStatus $primaryDomainStatus,
        public array $deployments = [],
        public array $availableActions = [],
    ) {
    }

    public function addDeployment(Microsoft365DeploymentDTO $microsoft365DeploymentDto): self
    {
        $this->deployments[] = $microsoft365DeploymentDto;

        return $this;
    }
}
