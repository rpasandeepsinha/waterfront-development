<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Actions;

use Illuminate\Support\ItemNotFoundException;
use InvalidArgumentException;
use Waterfront\Domain\Mailer\MailerInterface;
use Waterfront\Domain\Mailer\Templates\SubscriptionContractUpdated;
use Waterfront\Domain\Products\DTO\PriceRequest;
use Waterfront\Domain\Products\DTO\ProlongationPriceRequest;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\ProductPrice\PriceResolver;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Models\SubscriptionMutation;

class ExtendContractAction
{
    public function __construct(
        private readonly PriceResolver $priceResolver,
        private readonly MailerInterface $mailer,
    ) {
    }

    /**
     * @param positive-int          $billingPeriod
     * @param positive-int          $contractPeriod
     * @param non-negative-int|null $renewalPrice
     *
     * @throws ItemNotFoundException
     */
    public function execute(
        Subscription $subscription,
        int $billingPeriod,
        int $contractPeriod,
        ?int $renewalPrice,
        ?Product $product,
    ): SubscriptionMutation {
        $subscription->loadMissing('customer');

        if (
            $subscription->contract_period === $contractPeriod
            && $subscription->billing_period === $billingPeriod
            && $renewalPrice === null
            && $product === null
        ) {
            throw new InvalidArgumentException(
                "Can't extend contract with same values as already on subscription, normal renew scenario will take place.",
            );
        }

        $productToFetchPricesFor = $product ?? $subscription->product;

        $priceRequest = new PriceRequest([new ProlongationPriceRequest(
            $productToFetchPricesFor,
        )], $subscription->customer);
        $priceList = $this->priceResolver->getPriceList($priceRequest);

        $price = $priceList->getProductPrice($productToFetchPricesFor->slug, $contractPeriod, $billingPeriod);

        $mutation = new SubscriptionMutation();
        $mutation->gross_price = $price->regularPrice;
        $mutation->net_price = $renewalPrice ?? $price->regularPrice;
        $mutation->contract_period = $contractPeriod;
        $mutation->billing_period = $billingPeriod;
        $mutation->mutated_at = null;

        $mutation->subscription()->associate($subscription);

        if (! is_null($product)) {
            $mutation->product_id = $product->id;

            $mutation->save();
        } else {
            $mutation->product()->associate($subscription->product);

            $mutation->save();

            $customer = $subscription->customer;
            $this->mailer->send(
                [$customer],
                new SubscriptionContractUpdated(
                    $contractPeriod,
                    $billingPeriod,
                    $subscription->domain ?? '',
                ),
            );
        }

        return $mutation;
    }
}
