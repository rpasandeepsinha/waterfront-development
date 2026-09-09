<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\ItemNotFoundException;
use Waterfront\Domain\Pricing\Models\SubscriptionPrice;
use Waterfront\Domain\Products\DTO\PriceList;
use Waterfront\Domain\Subscriptions\DTO\NextInvoicePriceDTO;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Configuration\Configuration;

class GetNextInvoicePriceAction
{
    private readonly int $invoicingAheadDays;

    public function __construct(
        private readonly GetRenewalInfoAction $getRenewalInfoAction,
        private readonly IsSubscriptionPeriodEntirelyInvoicedAction $isSubscriptionPeriodEntirelyInvoiced,
        Configuration $configuration,
    ) {
        $this->invoicingAheadDays = $configuration->getAsInteger('constants.invoice-ahead-days');
    }

    /**
     * If the next billing date HAS NOT surpassed the end yet and needs to be invoiced, we check if there is a
     * precalculated price for the next billing cycle, otherwise we take the existing subscription net_price. If the
     * next billing date HAS surpassed the subscription end date we will fetch the renewal info.
     *
     * @throws ItemNotFoundException
     */
    public function execute(Subscription $subscription, ?PriceList $priceList = null): NextInvoicePriceDTO
    {
        $invoiceStartDate = $subscription->next_billing_date;

        if (! $this->isSubscriptionPeriodEntirelyInvoiced->execute($subscription)) {
            $invoiceEndDate = $this->getInvoiceEndDate(
                $subscription->next_billing_date,
                $subscription->billing_period,
                $subscription->end_date,
            );

            assert($subscription->activePrice instanceof SubscriptionPrice);

            $nextPrice = $subscription->prices()
                ->where('valid_from', '>', $subscription->activePrice->valid_from)
                ->orderBy('valid_from')
                ->first();

            return new NextInvoicePriceDTO(
                $subscription->product,
                $subscription->billing_period,
                $subscription->gross_price ?? 0,
                $nextPrice->net_price ?? $subscription->net_price ?? 0,
                $nextPrice,
                $invoiceStartDate,
                $invoiceEndDate,
            );
        }

        $renewalInfo = $this->getRenewalInfoAction->execute($subscription, $priceList);
        $invoiceEndDate = $this->getInvoiceEndDate(
            $subscription->next_billing_date,
            $renewalInfo->billingPeriod,
            $renewalInfo->endDate,
        );

        return new NextInvoicePriceDTO(
            $renewalInfo->product,
            $renewalInfo->billingPeriod,
            $renewalInfo->grossPrice,
            $renewalInfo->netPrice,
            null,
            $invoiceStartDate,
            $invoiceEndDate,
        );
    }

    private function getInvoiceEndDate(
        CarbonImmutable $invoiceStartDate,
        int $billingPeriod,
        CarbonImmutable $currentSubscriptionEndDate
    ): CarbonImmutable {
        $invoiceEndDate = $invoiceStartDate->addMonths($billingPeriod)->startOfDay();

        /**
         * Only if the billing period is shorter than the contract period:
         * If this is the last billing period of this contract period, the end date of
         * this invoice line should become the same as the end date of the subscription.
         *
         * Example: If you started your subscription after the 28th day on the month and are billed monthly,
         * it is very likely that the next billing date will switch to the 1st, 2nd or 3rd of the month.
         * That's why we allow a small margin (4 + renewal days) and use the end date of the subscription
         * if it's within that margin.
         */
        if ($currentSubscriptionEndDate->diffInDays($invoiceEndDate, true) <= (4 + $this->invoicingAheadDays)) {
            $invoiceEndDate = $currentSubscriptionEndDate;
        }

        return $invoiceEndDate;
    }
}
