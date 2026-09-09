<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\Transfers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Transfers\Models\Transfer;

/**
 * @property Transfer $resource
 */
class TransferResource extends JsonResource
{
    /**
     * @return array<mixed>
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['subscriptions', 'toCustomer', 'fromCustomer']);

        $transferSubscriptions = $this->resource->subscriptions;

        $availableActions = [];
        if ($this->resource->isAccepted() &&
            array_any((array) $transferSubscriptions->getIterator(), fn (Subscription $subscription, int $key) => $subscription->pivot->failed_at !== null)) {
            $availableActions[] = 'canRetry';
        }

        return [
            'uuid' => $this->resource->uuid,
            'fromCustomer' => $this->customerSummary($this->resource->fromCustomer),
            'toCustomer' => $this->customerSummary($this->resource->toCustomer),
            'created_at' => $this->resource->created_at,
            'canceled_at' => $this->resource->canceled_at,
            'rejected_at' => $this->resource->rejected_at,
            'accepted_at' => $this->resource->accepted_at,
            'completed_at' => $this->resource->completed_at,
            'started_at' => $this->resource->started_at,
            'subscriptions' => SubscriptionResource::collection($transferSubscriptions),
            'available_actions' => $availableActions,
        ];
    }

    /**
     * @return array<string, int|string>
     */
    private function customerSummary(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'customer_number' => $customer->customer_number,
            'full_name' => $customer->contact_name,
        ];
    }
}
