<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Resources;

use Illuminate\Database\Eloquent\Collection;
use JsonException;
use Waterfront\Apps\API\Waterfront\Policies\SubscriptionPolicy;
use Waterfront\Domain\Subscriptions\Actions\GetNextInvoicePriceAction;
use Waterfront\Domain\Subscriptions\Helpers\DetermineSubscriptionActiveStatusHelper;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Common\DateTimeFormat;

readonly class DomainAndCustomerRelationSubscriptionResource
{
    public function __construct(
        private SubscriptionPolicy $subscriptionPolicy,
        private ProductPresenter $productPresenter,
        private GetNextInvoicePriceAction $getNextInvoicePriceAction,
        private SubscriptionPresenter $subscriptionPresenter,
    ) {
    }

    /**
     * @param Collection<int, Subscription> $subscriptions
     *
     * @return array<mixed>
     */
    public function toArray(Collection $subscriptions): array
    {
        $subscriptionArray = [];
        foreach ($subscriptions as $subscription) {
            $subscriptionArray[] = $this->subscriptionToArray($subscription);
        }

        return $subscriptionArray;
    }

    /**
     * @param Collection<int,Subscription> $subscriptions
     *
     * @throws JsonException
     */
    public function toJson(Collection $subscriptions): string
    {
        $subscriptionArray = [];
        foreach ($subscriptions as $subscription) {
            $subscriptionArray[] = $this->subscriptionToArray($subscription);
        }

        return json_encode($subscriptionArray, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    private function subscriptionToArray(Subscription $subscription): array
    {
        $subscription->loadMissing(['product', 'product.productGroup']);

        return [
            'id' => $subscription->id,
            'uuid' => $subscription->uuid,
            'product' => $this->productPresenter->toArray($subscription->product),
            'domain' => $subscription->domain,
            'net_price' => $subscription->net_price,
            'billing_period' => $subscription->billing_period,
            'contract_period' => $subscription->contract_period,
            'start_date' => $subscription->start_date->toW3cString(),
            'end_date' => $subscription->end_date->toW3cString(),
            'administrative_status' => $subscription->administrative_status,
            'status' => DetermineSubscriptionActiveStatusHelper::resolve($subscription),
            'technical_status' => $subscription->technical_status,
            'available_actions' => $this->subscriptionPolicy->getAvailableActions($subscription),
            'children' => $this->subscriptionPresenter->collectionToArray($subscription->children),
            'next_invoice' => [
                'date' => $subscription->next_billing_date->format(DateTimeFormat::DATE),
                'price' => $this->getNextInvoicePriceAction->execute($subscription)->netPrice,
            ],
        ];
    }
}
