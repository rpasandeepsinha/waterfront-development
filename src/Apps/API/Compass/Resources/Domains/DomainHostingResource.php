<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\Domains;

use Illuminate\Container\Container;
use Illuminate\Http\Resources\Json\JsonResource;
use RuntimeException;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Hosting\Services\HostingDeploymentService;

/** @property HostingDeployment $resource */
class DomainHostingResource extends JsonResource
{
    /** @return array<string, int|string|null> */
    public function toArray($request): array
    {
        /** @var HostingDeploymentService $hostingDeploymentService */
        $hostingDeploymentService = Container::getInstance()->make(HostingDeploymentService::class);

        $providerArray = $this->providerAndSlug();
        try {
            $providerArray['username'] =
                $hostingDeploymentService->getUsername($this->resource) ?? $hostingDeploymentService->getMailUsername($this->resource);
        } catch (RuntimeException) {
            $providerArray['username'] = null;
        }

        $providerArray['last_result'] = $this->resource->last_created_result;
        $providerArray['id'] = $this->resource->id;

        return $providerArray;
    }

    /** @return array{provider:string|null, server_id: int|null, server_ip: string|null} */
    private function providerAndSlug(): array
    {
        if ($this->resource->sitebuilderProvider !== null) {
            return [
                'provider' => $this->resource->sitebuilderProvider->slug->value,
                'server_id' => $this->resource->basekitServer?->id,
                'server_ip' => $this->resource->basekitServer?->ipv4,
                'server_url' => $this->resource->basekitServer?->hostname,
                'basekit_user_ref' => $this->resource->basekit_user_ref,
                'basekit_site_ref' => $this->resource->basekit_site_ref,
                'mail_server_id' => $this->resource->mailOnlyServer?->id,
                'mail_provider' => $this->resource->mailProvider?->slug->value,
                'mail_server_url' => $this->resource->mailOnlyServer?->hostname,
            ];
        }

        if ($this->resource->mailProvider !== null) {
            return [
                'provider' => $this->resource->mailProvider->slug->value,
                'server_id' => $this->resource->mailOnlyServer?->id,
                'server_ip' => $this->resource->mailOnlyServer?->ipv4,
                'server_url' => $this->resource->mailOnlyServer?->hostname,
            ];
        }

        return [
            'provider' => $this->resource->provider?->slug->value,
            'server_id' => $this->resource->server?->id,
            'server_ip' => $this->resource->server?->ipv4,
            'server_url' => $this->resource->server?->hostname,
        ];
    }
}
