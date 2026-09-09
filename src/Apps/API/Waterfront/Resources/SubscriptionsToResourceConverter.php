<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Resources;

use Illuminate\Support\Collection;
use Waterfront\Apps\API\Waterfront\Policies\SubscriptionPolicy;
use Waterfront\Domain\Products\DTO\PriceRequest;
use Waterfront\Domain\Products\DTO\RegistrationPriceRequest;
use Waterfront\Domain\Products\ProductPrice\PriceResolver;
use Waterfront\Domain\Subscriptions\Actions\GetNextInvoicePriceAction;
use Waterfront\Domain\Subscriptions\Helpers\DetermineSubscriptionActiveStatusHelper;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Transfers\Services\TransferService;
use Waterfront\Infra\Common\DateTimeFormat;

class SubscriptionsToResourceConverter
{
    public function __construct(
        private readonly GetNextInvoicePriceAction $getNextInvoicePriceAction,
        private readonly SubscriptionPolicy $subscriptionPolicy,
        private readonly PriceResolver $priceResolver,
        private readonly TransferService $transferService,
    ) {
    }

    /**
     * @param Collection<int, Subscription> $collection
     *
     * @return mixed[]
     */
    public function toArray(Collection $collection): array
    {
        if ($collection->count() < 1) {
            return [];
        }

        $nextInvoicePrices = $this->getPriceList($collection);

        return $collection
            ->map(fn (Subscription $subscription) => $this->parseIndexSubscription($subscription, $nextInvoicePrices[$subscription->id] ?? null))
            ->toArray();
    }

    /**
     * @param Collection<int, Subscription> $collection
     *
     * @return array<int, int|null>
     */
    private function getPriceList(Collection $collection): array
    {
        $uniqueProducts = $collection->map(fn (Subscription $subscription) => $subscription->product)->unique();
        $productPriceRequests = array_map(fn ($product) => new RegistrationPriceRequest($product), $uniqueProducts->all());
        $priceList = $this->priceResolver->getPriceList(new PriceRequest($productPriceRequests, $collection->firstOrFail()->customer));

        $return = [];

        foreach ($collection as $subscription) {
            $return[$subscription->id] = $this->getNextInvoicePriceAction->execute($subscription, $priceList)->netPrice;
        }

        return $return;
    }

    /**
     * @return array<mixed>
     */
    private function parseIndexSubscription(Subscription $subscription, int|null $nextInvoicePrice): array
    {
        $type = $subscription->product->productGroup->slug;

        $labels = [];
        foreach ($subscription->labels as $label) {
            $labels[] = ['label' => $label->value, 'id' => $label->id];
        }

        return [
            'id'                    => $subscription->id,
            'uuid'                  => $subscription->uuid,
            'customer_id'           => $subscription->customer_id,
            'product_name'          => $subscription->product->name,
            'product_slug'          => $subscription->product->slug,
            'domain'                => $subscription->domain,
            'type'                  => $type->value,
            'technical_status'      => $subscription->technical_status,
            'administrative_status' => $subscription->administrative_status,
            'active_status'         => DetermineSubscriptionActiveStatusHelper::resolve($subscription),
            'status'         => DetermineSubscriptionActiveStatusHelper::resolve($subscription),
            'billing_period'        => $subscription->billing_period,
            'contract_period'       => $subscription->contract_period,
            'start_date'            => $subscription->start_date->toW3cString(),
            'end_date'              => $subscription->end_date->toW3cString(),
            'in_transfer'           => $this->transferService->hasOpenTransfer($subscription),
            'available_actions'     => $this->subscriptionPolicy->getAvailableActions($subscription),
            'labels'                => $labels,
            'next_invoice' => [
                'date' => $subscription->next_billing_date->format(DateTimeFormat::DATE),
                'price' => $nextInvoicePrice,
            ],
        ];
    }
}
