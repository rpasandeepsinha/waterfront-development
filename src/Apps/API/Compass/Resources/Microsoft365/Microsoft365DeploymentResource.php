<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\Microsoft365;

use JsonException;
use Waterfront\Domain\Microsoft365\Models\Microsoft365Deployment;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class Microsoft365DeploymentResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Microsoft365Deployment $microsoft365Deployment): array
    {
        $children = $microsoft365Deployment->subscription->children;

        $subscription = $children->isEmpty()
            ? $microsoft365Deployment->subscription
            : $children->firstOrFail();

        $seatCountsByStatus = $children->countBy('administrative_status');

        return [
            'id' => $microsoft365Deployment->id,
            'subscription_uuid' => $microsoft365Deployment->subscription->uuid,
            'tenant_name' => $microsoft365Deployment->microsoft365CustomerInfo->tenant_name,
            'product' => $subscription->product->name,
            'period' => $subscription->contract_period,
            'active_seats' => $seatCountsByStatus->get(AdministrativeStatus::ACTIVE->value, 0),
            'canceled_seats' => $seatCountsByStatus->get(AdministrativeStatus::CANCELED->value, 0),
            'kpn_order_id' => $microsoft365Deployment->kpn_order_id,
            'kpn_status' => $microsoft365Deployment->kpn_status->value,
            'kpn_start_date' => $microsoft365Deployment->kpn_start_date?->toW3cString(),
            'technical_status' => $microsoft365Deployment->technical_status,
        ];
    }

    /**
     * @throws JsonException
     */
    public function toJson(Microsoft365Deployment $microsoft365Deployment): string
    {
        return json_encode($this->toArray($microsoft365Deployment), flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    public function toDetailArray(Microsoft365Deployment $microsoft365Deployment): array
    {
        return [
            ...$this->toArray($microsoft365Deployment),
            'subscription' => $this->subscriptionSummary($microsoft365Deployment->subscription),
            'children' => $microsoft365Deployment->subscription->children
                ->map(fn (Subscription $child) => $this->subscriptionSummary($child))
                ->values()
                ->all(),
        ];
    }

    /**
     * @throws JsonException
     */
    public function toDetailJson(Microsoft365Deployment $microsoft365Deployment): string
    {
        return json_encode($this->toDetailArray($microsoft365Deployment), flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    private function subscriptionSummary(Subscription $subscription): array
    {
        return [
            'id' => $subscription->id,
            'domain' => $subscription->domain,
            'product' => $subscription->product->name,
            'administrative_status' => $subscription->administrative_status,
            'technical_status' => $subscription->technical_status,
        ];
    }
}
