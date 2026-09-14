<?php

declare(strict_types=1);

namespace Waterfront\Domain\Orders\Actions;

use Carbon\CarbonImmutable;
use Waterfront\Domain\OneTimeServices\Services\OneTimeServiceCreator;
use Waterfront\Domain\Orders\DTO\ProcessOrderLineItemDTO;
use Waterfront\Domain\Orders\Enums\OrderStatus;
use Waterfront\Domain\Orders\Exceptions\OrderLineItemNotProcessableException;
use Waterfront\Domain\Orders\Models\OrderLineItem;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Domain\Subscriptions\Services\SubscriptionService;

class ProcessOrderLineItemAction
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService,
        private readonly OneTimeServiceCreator $oneTimeServiceCreator,
        private readonly SubscriptionRepository $subscriptionRepository,
    ) {
    }

    /**
     * @throws OrderLineItemNotProcessableException
     */
    public function execute(OrderLineItem $orderLineItem, ProcessOrderLineItemDTO $processOrderLineItem): void
    {
        $orderLineItem->loadMissing(['order', 'product.productGroup']);

        if ($orderLineItem->processed_at !== null) {
            throw OrderLineItemNotProcessableException::alreadyProcessed();
        }

        if (in_array($orderLineItem->order->status, [OrderStatus::ON_HOLD, OrderStatus::ABUSE], true)) {
            throw OrderLineItemNotProcessableException::invalidOrderStatus();
        }

        if ($orderLineItem->product?->productGroup->slug === ProductGroupType::ONE_TIME_SERVICE) {
            $this->oneTimeServiceCreator->createFromOrderLineItem($orderLineItem);

            return;
        }

        $parentSubscription = $this->resolveParentSubscription($processOrderLineItem->parentSubscriptionId);

        $subscription = $this->subscriptionService->createSubscriptionFromOrderLineItem(
            $orderLineItem,
            $processOrderLineItem->manageSubscriptions,
        );

        $orderLineItem->subscription()->associate($subscription);

        $subscription->administrative_status = $processOrderLineItem->administrativeStatus->value;
        $subscription->technical_status = $processOrderLineItem->technicalStatus->value;

        if ($parentSubscription instanceof Subscription) {
            $subscription->parent()->associate($parentSubscription);
        }

        $subscription->save();

        $orderLineItem->processed_at = CarbonImmutable::now();
        $orderLineItem->save();
    }

    /**
     * @throws OrderLineItemNotProcessableException
     */
    private function resolveParentSubscription(?int $parentSubscriptionId): ?Subscription
    {
        if ($parentSubscriptionId === null) {
            return null;
        }

        $parentSubscription = $this->subscriptionRepository->findById($parentSubscriptionId);

        if (! $parentSubscription instanceof Subscription) {
            throw OrderLineItemNotProcessableException::parentSubscriptionNotFound();
        }

        return $parentSubscription;
    }
}
