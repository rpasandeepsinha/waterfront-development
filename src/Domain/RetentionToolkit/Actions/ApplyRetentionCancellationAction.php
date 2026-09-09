<?php

declare(strict_types=1);

namespace Waterfront\Domain\RetentionToolkit\Actions;

use Illuminate\Support\Collection;
use LogicException;
use Waterfront\Domain\Harbor\DTO\Services\Invoice\InvoiceBatchCrediter\InvoiceToCreditBatch;
use Waterfront\Domain\RetentionToolkit\DTO\RetentionOfferItemCalculationDTO;
use Waterfront\Domain\RetentionToolkit\DTO\RetentionOfferItemDTO;
use Waterfront\Domain\RetentionToolkit\Enums\CustomerType;
use Waterfront\Domain\Subscriptions\Actions\CancelSubscriptionsAction;
use Waterfront\Domain\Subscriptions\DTO\Cancellation;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelType;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Services\CreditSubscriptionService;
use Waterfront\Domain\Subscriptions\Services\SubscriptionRenewService;
use Webmozart\Assert\Assert;

readonly class ApplyRetentionCancellationAction
{
    public function __construct(
        private CreditSubscriptionService $creditSubscriptionService,
        private CancelSubscriptionsAction $cancelSubscriptionsAction,
        private SubscriptionRenewService $subscriptionRenewService,
    ) {
    }

    public function execute(
        CustomerType $customerType,
        RetentionOfferItemDTO $item,
        RetentionOfferItemCalculationDTO $result,
        Subscription $subscription,
    ): ?InvoiceToCreditBatch {
        $subscription->loadMissing('product', 'children');
        $cancellationDate = $result->cancellationDate;
        $cancelReason = $item->cancelReason;

        if ($cancellationDate === null || $cancelReason === null) {
            throw new LogicException('A calculated RF action must have cancellation details.');
        }

        $isLateBusinessCancellation = $customerType === CustomerType::BUSINESS && $cancellationDate->greaterThan($subscription->end_date);

        /*
         * In the edge case of a late business cancellation, renew first so cancellation
         * can be scheduled for the next contract end. Otherwise, the Cancellation DTO
         * used by CancelSubscriptionsAction clamps it to the current contract end.
         */
        if ($isLateBusinessCancellation) {
            $this->subscriptionRenewService->renew($subscription);
        }

        $creditTotal = $result->creditTotal;
        Assert::notNull($creditTotal);

        $shouldCreditRelatedInvoices = $cancelReason->allowedToCredit()
            && $creditTotal > 0;

        $cancellation = new Cancellation(
            subscriptions: new Collection([$subscription]),
            cancelReason: $cancelReason,
            cancelReasonOther: $item->cancelReasonOther,
            cancelType: SubscriptionCancelType::CANCEL_OTHER,
            selectedCancelEndDate: $cancellationDate,
            creditRelatedInvoices: $shouldCreditRelatedInvoices,
        );

        $invoiceLinesToCredit = null;

        if ($cancellation->shouldCreditRelatedInvoices()) {
            $invoiceLinesToCredit = $this->creditSubscriptionService
                ->getInvoiceLinesToCreditBatch($cancellation);
        }

        $this->cancelSubscriptionsAction->execute($cancellation);

        return $invoiceLinesToCredit;
    }
}
