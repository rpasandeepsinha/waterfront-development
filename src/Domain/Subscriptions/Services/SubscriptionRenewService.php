<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\LazyCollection;
use Psr\Log\LoggerInterface;
use Throwable;
use Waterfront\Domain\Pricing\Repositories\PriceRepository;
use Waterfront\Domain\Pricing\Services\PricePersistService;
use Waterfront\Domain\Products\DTO\Price;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Subscriptions\Actions\GetRenewalInfoAction;
use Waterfront\Domain\Subscriptions\Enums\ProductChangeType;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionChangeStatus;
use Waterfront\Domain\Subscriptions\Helpers\RenewalHelper;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Models\SubscriptionMutation;
use Waterfront\Domain\Subscriptions\Repositories\ProductAllowedChangeRepository;
use Waterfront\Support\Enums\LoggingContextKeys;

class SubscriptionRenewService
{
    public function __construct(
        private readonly GetRenewalInfoAction $getRenewalInfoAction,
        private readonly LoggerInterface $logger,
        private readonly SubscriptionChangeService $subscriptionChangeService,
        private readonly ProductAllowedChangeRepository $productAllowedChangeRepository,
        private readonly PricePersistService $pricePersistService,
        private readonly PriceRepository $priceRepository,
    ) {
    }

    public function renewSubscription(Subscription $subscription): void
    {
        $renewalPriceDTO = $this->getRenewalInfoAction->execute($subscription, null);

        //Handle mutations with different products and might require a technical change.
        if ($renewalPriceDTO->appliedSubscriptionMutation instanceof SubscriptionMutation && $renewalPriceDTO->appliedSubscriptionMutation->product->id !== $subscription->product->id) {
            $this->processProductChange($subscription, $renewalPriceDTO->product, $renewalPriceDTO->appliedSubscriptionMutation);
        }

        $subscription->product_uuid = $renewalPriceDTO->product->uuid;
        $subscription->contract_period = $renewalPriceDTO->contractPeriod;
        $subscription->billing_period = $renewalPriceDTO->billingPeriod;
        $subscription->end_date = $renewalPriceDTO->endDate;

        if ($renewalPriceDTO->price instanceof Price) {
            $this->pricePersistService->persistSubscriptionPrice($subscription, $renewalPriceDTO->price, CarbonImmutable::now());
        } else {
            $subscription->gross_price = $renewalPriceDTO->grossPrice;
            $subscription->net_price = $renewalPriceDTO->netPrice;
        }

        if ($renewalPriceDTO->appliedSubscriptionMutation !== null) {
            $this->registerMutationAsApplied($renewalPriceDTO->appliedSubscriptionMutation);
        }

        $subscription->save();
    }

    /**
     * Update end_date and price of subscription.
     */
    public function renew(Subscription $subscription): Subscription
    {
        $this->logger->info(
            sprintf(
                'Renewing subscription %s (%s)',
                $subscription->domain ?? '',
                $subscription->uuid,
            ),
            [
                LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
            ]
        );

        try {
            $this->renewSubscription($subscription);
            $subscription->refresh();
            $this->renewChildren($subscription);
        } catch (Throwable $throwable) {
            $this->logger->critical(
                sprintf(
                    'Failed renewing subscription %s (%s)',
                    $subscription->domain ?? '',
                    $subscription->uuid,
                ),
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                    LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
                    LoggingContextKeys::EXCEPTION => $throwable,
                ]
            );
            throw $throwable;
        }

        return $subscription;
    }

    private function processProductChange(Subscription $subscription, Product $newProduct, SubscriptionMutation $mutation): void
    {
        $newProduct->loadMissing('productGroup');

        if ($newProduct->productGroup->slug !== ProductGroupType::HOSTING) {
            return;
        }

        if ($this->productAllowedChangeRepository->isProductChangeAllowed(ProductChangeType::UPGRADE, $subscription->product, $mutation->product)) {
            $this->subscriptionChangeService->storeChangeRecord($subscription, $newProduct, ProductChangeType::UPGRADE, SubscriptionChangeStatus::REQUESTED);
        } elseif ($this->productAllowedChangeRepository->isProductChangeAllowed(ProductChangeType::DOWNGRADE, $subscription->product, $mutation->product)) {
            $this->subscriptionChangeService->storeChangeRecord($subscription, $newProduct, ProductChangeType::DOWNGRADE, SubscriptionChangeStatus::REQUESTED);
        } else {
            return;
        }

        // There might be precalculated subscription prices for future billing cycles. Those are no longer valid,
        // because they were for the previous product. The result is that the subscription net_price is used for
        // the following billing cycles.
        $this->priceRepository->deleteFutureSubscriptionPrices($subscription);

        $this->logger->info(sprintf(
            'Applying technical processing date to mutation for subscription %s (%s)',
            $subscription->domain ?? '',
            $subscription->uuid
        ), [
                LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
        ]);

        $mutation->process_technical_at = $subscription->end_date;
        $mutation->save();
    }

    /**
     * Renew child subscriptions linked to the parent subscription, same end date as the parent.
     */
    private function renewChildren(Subscription $parent): void
    {
        /** @var LazyCollection<int, Subscription> $children */
        $children = Subscription::query()
            ->where('parent_subscription_id', $parent->id)
            ->cursor();

        foreach ($children as $child) {
            $this->logger->info(
                sprintf(
                    'Renewing child subscription %s (%s)',
                    $child->domain ?? '',
                    $child->uuid,
                ),
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $child->uuid,
                    LoggingContextKeys::DOMAIN_NAME => $child->domain,
                ]
            );

            if (! RenewalHelper::isChildRenewable($child)) {
                continue;
            }

            $this->syncChildAndParent($parent, $child);
            $this->renewSubscription($child);

            $child->save();
        }
    }

    private function registerMutationAsApplied(SubscriptionMutation $subscriptionMutation): void
    {
        $this->logger->info(
            sprintf(
                'Applied mutation for subscription %s (%s)',
                $subscriptionMutation->subscription->domain ?? '',
                $subscriptionMutation->subscription->uuid,
            ),
            [
                LoggingContextKeys::SUBSCRIPTION_UUID => $subscriptionMutation->subscription->uuid,
                LoggingContextKeys::DOMAIN_NAME => $subscriptionMutation->subscription->domain,
            ]
        );

        $subscriptionMutation->mutated_at = CarbonImmutable::now();
        $subscriptionMutation->save();
    }

    private function syncChildAndParent(Subscription $parent, Subscription $child): void
    {
        if (
            $parent->billing_period === $child->billing_period
            && $parent->contract_period === $child->contract_period
        ) {
            return;
        }

        $child->billing_period = $parent->billing_period;
        $child->contract_period = $parent->contract_period;
    }
}
