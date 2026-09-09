<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Actions;

use Carbon\CarbonImmutable;
use Waterfront\Domain\Pricing\Repositories\PriceRepository;
use Waterfront\Domain\Products\DTO\PriceList;
use Waterfront\Domain\Products\DTO\PriceRequest;
use Waterfront\Domain\Products\DTO\ProlongationPriceRequest;
use Waterfront\Domain\Products\ProductPrice\PriceResolver;
use Waterfront\Domain\Subscriptions\DTO\RenewalInfoDTO;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionMutationRepository;
use Webmozart\Assert\Assert;

class GetRenewalInfoAction
{
    /**
     * @var RenewalInfoDTO[]
     *
     * This "poor mans cache" is used for subsequent calls to this service with the same properties.
     *
     * A good example is Microsoft365 billing that calls this service once for each child subscription under a parent.
     * As these child subscriptions share the same properties, we can cache the result and return it for subsequent calls.
     */
    private array $cachedInfos = [];

    public function __construct(
        private readonly SubscriptionMutationRepository $subscriptionMutationRepository,
        private readonly PriceResolver $priceResolver,
        private readonly PriceRepository $priceRepository,
    ) {
    }

    /**
     * If there is an open upcoming mutation use that for the renewal info.
     * Else use the prolongation price form the price list and apply that on the invoice.
     */
    public function execute(Subscription $subscription, ?PriceList $priceList): RenewalInfoDTO
    {
        $renewalStartDate = $subscription->end_date;
        $subscription->loadMissing(['parent', 'activePrice.components']);
        $renewalEndDate = $this->getRenewalEndDate($renewalStartDate, $subscription->contract_period, $subscription->parent);

        $openMutation = $this->subscriptionMutationRepository->findOpenMutation($subscription);
        if ($openMutation !== null) {
            $renewalEndDate = $subscription->end_date->addMonths($openMutation->contract_period);

            return new RenewalInfoDTO(
                $openMutation->product,
                $openMutation->contract_period,
                $openMutation->billing_period,
                $openMutation->gross_price,
                $openMutation->net_price,
                $renewalStartDate,
                $renewalEndDate,
                $openMutation
            );
        }

        if ($this->priceRepository->hasIndefiniteCustomPrice($subscription)) {
            return new RenewalInfoDTO(
                $subscription->product,
                $subscription->contract_period,
                $subscription->billing_period,
                $subscription->gross_price,
                $subscription->net_price,
                $renewalStartDate,
                $renewalEndDate,
            );
        }

        $cacheHash = $this->getSubscriptionCacheHash($subscription);

        if (array_key_exists($cacheHash, $this->cachedInfos)) {
            return $this->cachedInfos[$cacheHash];
        }

        $priceRequest = new PriceRequest([new ProlongationPriceRequest($subscription->product)], $subscription->customer);
        $priceList ??= $this->priceResolver->getPriceList($priceRequest);
        $price = $priceList->getProductPrice(
            productSlug: $subscription->product->slug,
            contractPeriod: $subscription->contract_period,
            billingPeriod: $subscription->billing_period,
        );

        Assert::natural($price->calculatedPrice);

        $this->cachedInfos[$cacheHash] = new RenewalInfoDTO(
            $subscription->product,
            $subscription->contract_period,
            $subscription->billing_period,
            $price->regularPrice,
            $price->calculatedPrice,
            $renewalStartDate,
            $renewalEndDate,
            null,
            $price,
        );

        return $this->cachedInfos[$cacheHash];
    }

    private function getRenewalEndDate(CarbonImmutable $renewalStartDate, int $contractPeriod, ?Subscription $parentSubscription): CarbonImmutable
    {
        /*
         * Child subscriptions should be in sync with their parent.
         * Because the parent subscription is renewed before the child, we can use that end date if it's already
         * after the current child's end date.
         */
        if ($parentSubscription !== null && $parentSubscription->end_date >= $renewalStartDate) {
            return $parentSubscription->end_date;
        }

        return $renewalStartDate->addMonths($contractPeriod);
    }

    private function getSubscriptionCacheHash(Subscription $subscription): string
    {
        return $subscription->product_uuid . $subscription->start_date . $subscription->end_date . $subscription->billing_period . $subscription->contract_period;
    }
}
