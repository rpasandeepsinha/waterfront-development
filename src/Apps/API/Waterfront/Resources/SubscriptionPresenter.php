<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Resources;

use Illuminate\Database\Eloquent\Collection;
use JsonException;
use Waterfront\Apps\API\Waterfront\Policies\SubscriptionPolicy;
use Waterfront\Domain\Subscriptions\Actions\GetNextInvoicePriceAction;
use Waterfront\Domain\Subscriptions\Helpers\DetermineSubscriptionActiveStatusHelper;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Transfers\Services\TransferService;
use Waterfront\Infra\Common\DateTimeFormat;

class SubscriptionPresenter
{
    public function __construct(
        private readonly SubscriptionPolicy $subscriptionPolicy,
        private readonly GetNextInvoicePriceAction $getNextInvoicePriceAction,
        private readonly ProductPresenter $productPresenter,
        private readonly TransferService $transferService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Subscription $subscription): array
    {
        $subscription->loadMissing(['customer', 'labels', 'mutations.product', 'product', 'transfers', 'hostingDeployment']);

        $labels = [];
        foreach ($subscription->labels as $label) {
            $labels[] = ['label' => $label->value, 'id' => $label->id];
        }

        return [
            'id'                    => $subscription->id,
            'uuid'                  => $subscription->uuid,
            'product'               => $this->productPresenter->toArray($subscription->product),
            'net_price'             => $subscription->net_price,
            'mutations'             => $subscription->mutations->toArray(),
            'domain'                => $subscription->domain,
            'administrative_status' => $subscription->administrative_status,
            'status'                => DetermineSubscriptionActiveStatusHelper::resolve($subscription),
            'available_actions'     => $this->subscriptionPolicy->getAvailableActions($subscription),
            'billing_period'        => $subscription->billing_period,
            'contract_period'       => $subscription->contract_period,
            'start_date'            => $subscription->start_date->toW3cString(),
            'end_date'              => $subscription->end_date->toW3cString(),
            'in_transfer'           => $this->transferService->hasOpenTransfer($subscription),
            'children'              => $this->collectionToArray($subscription->children),
            'has_service_plus'      => $this->subscriptionPolicy->hasServicePlan($subscription),
            'technical_status'      => $subscription->technical_status,
            'labels'                => $labels,
            'next_invoice' => [
                'date' => $subscription->next_billing_date->format(DateTimeFormat::DATE),
                'price' => $this->getNextInvoicePriceAction->execute($subscription)->netPrice,
            ],
        ];
    }

    /**
     * @throws JsonException
     */
    public function toJson(Subscription $subscription): string
    {
        return json_encode($this->toArray($subscription), flags:JSON_THROW_ON_ERROR);
    }

    /**
     * @param Collection<int, Subscription> $subscription
     *
     * @return array<mixed>
     */
    public function collectionToArray(Collection $subscription): array
    {
        $array = [];

        $subscription->each(function (Subscription $subscription) use (&$array) {
            $array[] = $this->toArray($subscription);
        });

        return $array;
    }
}
