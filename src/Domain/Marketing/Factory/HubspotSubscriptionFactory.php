<?php

declare(strict_types=1);

namespace Waterfront\Domain\Marketing\Factory;

use Waterfront\Domain\OneTimeServices\Models\OneTimeService;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Services\CancellationFlowService;
use Waterfront\Infra\Common\DateTimeFormat;
use Waterfront\Infra\HubspotClient\DTO\HubspotSubscriptionDTO;

class HubspotSubscriptionFactory
{
    public function __construct(
        private readonly CancellationFlowService $cancellationFlowService,
    ) {
    }

    public function createDTOFromSubscription(Subscription $subscription): HubspotSubscriptionDTO
    {
        $customer = $subscription->customer;

        $cancellationFlowReason = $this->cancellationFlowService->getCancellationFlowReason($subscription);

        return new HubspotSubscriptionDTO(
            hubspotId: null,
            sandwaveId: (string) $subscription->id,
            uuid: $subscription->uuid,
            parentSubscriptionId: $subscription->parent_subscription_id === null
                ? null
                : (string) $subscription->parent_subscription_id,
            domain: $subscription->domain,
            administrativeStatus: $subscription->administrative_status,
            technicalStatus: $subscription->technical_status,
            startDate: $subscription->start_date->format(DateTimeFormat::DATE),
            endDate: $subscription->end_date->format(DateTimeFormat::DATE),
            cancelDate: $subscription->cancel_date?->format(DateTimeFormat::DATE),
            cancelReason: $subscription->cancel_reason,
            grossPrice: (string) $subscription->gross_price,
            netPrice: (string) $subscription->net_price,
            billingPeriod: (string) $subscription->billing_period,
            contractPeriod: (string) $subscription->contract_period,
            productGroupName: $subscription->product->productGroup->name,
            productGroupSlug: $subscription->product->productGroup->slug->value,
            productName: $subscription->product->name,
            productSlug: $subscription->product->slug,
            customerNumber: (string) $customer->customer_number,
            nextBillingDate: $subscription->next_billing_date->format(DateTimeFormat::DATE),
            swOrderUuid: $subscription->orderLineItem?->order->uuid,
            otsAmount: null,
            otsDiscountPercentage: null,
            otsExecutionDate: null,
            otsStatus: null,
            cancellationFlowReason: $cancellationFlowReason,
            switchContact: false,
            experimentSlug: $subscription->experiments()->first()?->slug->value,
        );
    }

    public function createDTOFromOneTimeService(OneTimeService $oneTimeService): HubspotSubscriptionDTO
    {
        $customer = $oneTimeService->customer;

        return new HubspotSubscriptionDTO(
            hubspotId: null,
            sandwaveId: (string) $oneTimeService->id,
            uuid: (string) $oneTimeService->uuid,
            parentSubscriptionId: null,
            domain: $oneTimeService->subscription->domain,
            administrativeStatus: '',
            technicalStatus: null,
            startDate: '',
            endDate: '',
            cancelDate: null,
            cancelReason: null,
            grossPrice: (string) $oneTimeService->gross_price,
            netPrice: null,
            billingPeriod: '',
            contractPeriod: '',
            productGroupName: $oneTimeService->product->productGroup->name,
            productGroupSlug: $oneTimeService->product->productGroup->slug->value,
            productName: $oneTimeService->product->name,
            productSlug: $oneTimeService->product->slug,
            customerNumber: (string) $customer->customer_number,
            nextBillingDate: '',
            swOrderUuid: null,
            otsAmount: (string) $oneTimeService->amount,
            otsDiscountPercentage: (string) $oneTimeService->discount_percentage,
            otsExecutionDate: $oneTimeService->execution_date->format(DateTimeFormat::DATE),
            otsStatus: $oneTimeService->status->value,
            cancellationFlowReason: null,
            switchContact: false,
            experimentSlug: null,
        );
    }
}
