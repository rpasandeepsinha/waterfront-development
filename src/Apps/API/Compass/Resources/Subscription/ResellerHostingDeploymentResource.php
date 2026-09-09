<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\Subscription;

use JsonException;
use Waterfront\Apps\API\Waterfront\Resources\BaseTechnicalDeploymentResource;
use Waterfront\Domain\ResellerHosting\Models\ResellerHostingDeployment;

class ResellerHostingDeploymentResource
{
    public function __construct(
        private readonly BaseTechnicalDeploymentResource $baseTechnicalDeploymentResource,
    ) {
    }

    /**
     * @return array <string, mixed>
     */
    public function toArray(ResellerHostingDeployment $deployment): array
    {
        return [
            ...$this->baseTechnicalDeploymentResource->toArray($deployment),
            'server_name' => $deployment->server?->hostname,
            'username' => $deployment->relevant_username,
            'ftps_host' => $deployment->server?->hostname,
            'server_ipv4' => $deployment->server?->ipv4,
            'server_ipv6' => $deployment->server?->ipv6,
        ];
    }

    /**
     * @throws JsonException
     */
    public function toJson(ResellerHostingDeployment $deployment): string
    {
        return json_encode($this->toArray($deployment), flags:JSON_THROW_ON_ERROR);
    }
}
