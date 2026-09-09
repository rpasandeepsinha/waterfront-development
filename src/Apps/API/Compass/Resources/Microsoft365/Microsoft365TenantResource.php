<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\Microsoft365;

use Illuminate\Database\Eloquent\Collection;
use JsonException;
use Waterfront\Domain\Microsoft365\Models\Microsoft365CustomerInfo;
use Waterfront\Domain\Microsoft365\Models\Microsoft365Deployment;

class Microsoft365TenantResource
{
    public function __construct(
        private readonly Microsoft365DeploymentResource $microsoft365DeploymentResource,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Microsoft365CustomerInfo $microsoft365CustomerInfo): array
    {
        return [
            'id' => $microsoft365CustomerInfo->id,
            'tenant_name' => $microsoft365CustomerInfo->tenant_name,
            'tenant_id' => $microsoft365CustomerInfo->tenant_id,
            'tenant_order_id' => $microsoft365CustomerInfo->tenant_order_id,
            'primary_domain' => $microsoft365CustomerInfo->primary_domain,
            'primary_domain_status' => $microsoft365CustomerInfo->primary_domain_status?->value,
            'kpn_customer_id' => $microsoft365CustomerInfo->kpn_customer_id,
            'technical_status' => $microsoft365CustomerInfo->technical_status->value,
            'type' => $microsoft365CustomerInfo->type->value,
            'synced_at' => $microsoft365CustomerInfo->synced_at->toW3cString(),
            'mca_signed_at' => $microsoft365CustomerInfo->mca_signed_at?->toW3cString(),
            'deployments' => $microsoft365CustomerInfo->microsoft365Deployments
                ->map(fn (Microsoft365Deployment $deployment) => $this->microsoft365DeploymentResource->toArray($deployment))
                ->values()
                ->all(),
        ];
    }

    /**
     * @throws JsonException
     */
    public function toJson(Microsoft365CustomerInfo $microsoft365CustomerInfo): string
    {
        return json_encode($this->toArray($microsoft365CustomerInfo), flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @param Collection<int, Microsoft365CustomerInfo> $microsoft365CustomerInfos
     *
     * @return array<int, array<string, mixed>>
     */
    public function collectionToArray(Collection $microsoft365CustomerInfos): array
    {
        return $microsoft365CustomerInfos
            ->map(fn (Microsoft365CustomerInfo $microsoft365CustomerInfo) => $this->toArray($microsoft365CustomerInfo))
            ->values()
            ->all();
    }

    /**
     * @param Collection<int, Microsoft365CustomerInfo> $microsoft365CustomerInfos
     *
     * @throws JsonException
     */
    public function collectionToJson(Collection $microsoft365CustomerInfos): string
    {
        return json_encode($this->collectionToArray($microsoft365CustomerInfos), flags: JSON_THROW_ON_ERROR);
    }
}
