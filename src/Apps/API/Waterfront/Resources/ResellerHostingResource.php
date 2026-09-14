<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Resources;

use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Apps\API\Waterfront\Policies\SubscriptionPolicy;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Transfers\Services\TransferService;

/**
 * @property Subscription $resource
 */
class ResellerHostingResource extends JsonResource
{
    /**
     * @param Request $request
     *
     * @return array<string, array<int|string,array<string>|int|string|null>|bool|int|string|null>
     */
    public function toArray($request)
    {
        /** @var SubscriptionPolicy $subscriptionPolicy */
        $subscriptionPolicy = Container::getInstance()->make(SubscriptionPolicy::class);
        /** @var TransferService $transferService */
        $transferService = Container::getInstance()->make(TransferService::class);

        $activeStatus = $this->resource->administrative_status;

        switch ($this->resource->administrative_status) {
            case AdministrativeStatus::ACTIVE->value:
                if (in_array(
                    $this->resource->technical_status,
                    [TechnicalStatus::OK->value, DomainStatus::ACTIVE->value],
                    true,
                )) {
                    $activeStatus = 'active';
                } else {
                    $activeStatus = 'processing';
                }

                break;
            case AdministrativeStatus::CANCELED->value:
                if (in_array(
                    $this->resource->technical_status,
                    [TechnicalStatus::OK->value, DomainStatus::ACTIVE->value],
                    true,
                )) {
                    $activeStatus = 'canceled';
                }

                break;
        }

        return [
            'id' => $this->resource->id,
            'uuid' => $this->resource->uuid,
            'customer_id' => $this->resource->customer_id,
            'product_name' => $this->resource->product->name,
            'domain' => $this->resource->domain,
            'active_status' => $activeStatus,
            'technical_status' => $this->resource->technical_status,
            'administrative_status' => $this->resource->administrative_status,
            'period' => $this->resource->contract_period,
            'start_date' => $this->resource->start_date->toW3cString(),
            'end_date' => $this->resource->end_date->toW3cString(),
            'cancel_date' => $this->resource->cancel_date?->toW3cString(),
            'in_transfer' => $transferService->hasOpenTransfer($this->resource),
            'server_name' => $this->resource->resellerHostingDeployment?->server?->hostname,
            'username' => $this->resource->resellerHostingDeployment?->relevant_username,
            'hosting_provider' => $this->resource->resellerHostingDeployment?->provider->slug->value,
            'ftps_host' => $this->resource->resellerHostingDeployment?->server?->hostname,
            'available_actions' => $subscriptionPolicy->getAvailableActions($this->resource),
            'specs' => [
                'storage_type' => $this->resource->resellerHostingDeployment?->storage_type,
                'disk_space' => $this->resource->resellerHostingDeployment?->disk_space,
                'max_email_addresses' => $this->resource->resellerHostingDeployment?->max_email_addresses,
                'max_traffic' => $this->resource->resellerHostingDeployment?->max_traffic,
                'max_databases' => $this->resource->resellerHostingDeployment?->max_databases,
                'max_users' => $this->resource->resellerHostingDeployment?->max_users,
                'max_domains' => $this->resource->resellerHostingDeployment?->max_domains,
            ],
        ];
    }
}
