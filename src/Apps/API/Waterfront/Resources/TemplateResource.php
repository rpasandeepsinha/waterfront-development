<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource as Resource;
use Waterfront\Domain\DNS\Models\DnsCustomerTemplate;
use Waterfront\Domain\DNS\Transformers\DnsRecordResource;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Subscriptions\Models\Subscription;

/**
 * @property DnsCustomerTemplate $resource
 */
class TemplateResource extends Resource
{
    /**
     * @param Request $request
     *
     * @return array<mixed>
     */
    public function toArray($request): array
    {
        return [
            'id'   => $this->resource->id,
            'name' => $this->resource->name,
            'records' => DnsRecordResource::collection($this->resource->records),
            'domains' => $this->resource->domainDeployments
                ->map(fn (DomainDeployment $domainDeployment) => $domainDeployment->subscription)
                ->map(fn (Subscription $subscription) => $subscription->domain)
                ->toArray(),
        ];
    }
}
