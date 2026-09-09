<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Resources;

use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\App;
use Waterfront\Domain\Domains\Repositories\DomainDeploymentRepository;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Subscriptions\Helpers\DetermineSubscriptionActiveStatusHelper;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Transfers\Services\TransferService;

/**  @property Subscription $resource */
class SubscriptionOverviewResource extends JsonResource
{
    /**
     * @param Request $request
     *
     * @return array<mixed>
     */
    public function toArray($request): array
    {
        /** @var TransferService $transferService */
        $transferService = Container::getInstance()->make(TransferService::class);

        return [
            'id' => $this->resource->id,
            'uuid' => $this->resource->uuid,
            'parent_subscription_uuid' => $this->resource->parent?->uuid,
            'product_name' => $this->resource->product->name,
            'product_slug' => $this->resource->product->slug,
            'domain' => $this->resource->domain,
            'product_group' => $this->resource->product->productGroup->slug,
            'active_status' => DetermineSubscriptionActiveStatusHelper::resolve($this->resource),
            'labels' => $this->resource->labels->pluck('value')->toArray(),
            'end_date' => $this->resource->end_date->toW3cString(),
            'in_transfer' => $transferService->hasOpenTransfer($this->resource),
            'externalDomain' => $this->checkExternalDomain($this->resource->domain),
        ];
    }

    private function checkExternalDomain(?string $domain): bool
    {
        if ($domain === null) {
            return false;
        }

        /** @var DomainDeploymentRepository $domainDeploymentRepository */
        $domainDeploymentRepository = App::make(DomainDeploymentRepository::class);
        $domainDeployment = $domainDeploymentRepository->getDomainDeploymentByDomain($domain);

        return $domainDeployment === null || $domainDeployment->provider->slug === ProviderSlug::PLACEHOLDER;
    }
}
