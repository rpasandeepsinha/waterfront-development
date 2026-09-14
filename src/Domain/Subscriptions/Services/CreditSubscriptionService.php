<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Services;

use Carbon\CarbonImmutable;
use Psr\Log\LoggerInterface;
use SandwaveIo\HarborMessages\Message\Enum\InvoiceLineCreditReason;
use Waterfront\Domain\Harbor\DTO\Services\Invoice\InvoiceBatchCrediter\InvoiceToCreditBatch;
use Waterfront\Domain\Harbor\DTO\Services\Invoice\InvoiceCrediter\InvoiceToCredit;
use Waterfront\Domain\Harbor\Exceptions\HarborApiResponseException;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Domain\Invoices\Repositories\InvoiceRepository;
use Waterfront\Domain\Invoices\Services\CreditAndDispatchInvoiceLinesToHarbor;
use Waterfront\Domain\Subscriptions\DTO\Cancellation;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelReason;
use Waterfront\Domain\Subscriptions\Exceptions\CreditSubscriptionsException;
use Waterfront\Domain\Subscriptions\Exceptions\Services\SubscriptionCrediter\SubscriptionCrediterException;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Common\DateTimeFormat;
use Waterfront\Support\Enums\LoggingContextKeys;

class CreditSubscriptionService
{
    public function __construct(
        private readonly InvoiceRepository $invoiceLineRepository,
        private readonly CreditAndDispatchInvoiceLinesToHarbor $creditAndDispatchInvoiceLinesToHarbor,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @throws CreditSubscriptionsException
     */
    public function creditSubscriptions(
        Cancellation $cancellation,
    ): void {
        $this->logger->notice('Executing credit action', [
            LoggingContextKeys::META => [
                'cancellation' => [
                    'subscriptions' => $cancellation->getSubscriptions()->pluck('id')->all(),
                    'reason' => $cancellation->getCancelReason()->value,
                    'reason_other' => $cancellation->getCancelReasonOther(),
                    'type' => $cancellation->getCancelType()->value,
                    'type_other_date' => $cancellation->getSelectedCancellationEndDate()?->format(DateTimeFormat::DATE),
                    'credit' => (int) $cancellation->shouldCreditRelatedInvoices(),
                ],
            ],
        ]);

        $this->creditInvoiceLines(
            $this->getInvoiceLinesToCreditBatch($cancellation),
        );
    }

    /**
     * @throws CreditSubscriptionsException
     */
    public function creditInvoiceLines(
        InvoiceToCreditBatch $invoiceLinesToCreditBatch,
    ): void {
        if ($invoiceLinesToCreditBatch->count() === 0) {
            $this->logger->info('No related invoice lines found with a remainder to credit');

            return;
        }

        try {
            $this->creditAndDispatchInvoiceLinesToHarbor->creditAndDispatch($invoiceLinesToCreditBatch);
        } catch (SubscriptionCrediterException|HarborApiResponseException $exception) {
            throw new CreditSubscriptionsException(
                sprintf('Failed crediting related invoices of selected subscriptions: %s', $exception->getMessage()),
                0,
                $exception,
            );
        }
    }

    public function getInvoiceLinesToCreditBatch(Cancellation $cancellation): InvoiceToCreditBatch
    {
        $toCredit = new InvoiceToCreditBatch();

        foreach ($cancellation->getSubscriptions() as $subscription) {
            $subscriptionBatch = $this->getInvoiceLinesToCreditBatchFromDate(
                subscription: $subscription,
                creditFromDate: $this->getCreditFromDate($cancellation, $subscription),
                cancelReason: $cancellation->getCancelReason(),
            );

            foreach ($subscriptionBatch->getInvoicesToCredit() as $invoiceToCredit) {
                $toCredit->add($invoiceToCredit);
            }
        }

        return $toCredit;
    }

    public function getInvoiceLinesToCreditBatchFromDate(
        Subscription $subscription,
        CarbonImmutable $creditFromDate,
        SubscriptionCancelReason $cancelReason,
    ): InvoiceToCreditBatch {
        $toCredit = new InvoiceToCreditBatch();

        foreach ($this->getSubscriptionInvoiceLinesToCredit(
            subscription: $subscription,
            creditFromDate: $creditFromDate,
            cancelReason: $cancelReason,
        ) as $invoiceToCredit) {
            $toCredit->add($invoiceToCredit);
        }

        return $toCredit;
    }

    /**
     * It's important to note that one subscription may have multiple debit invoice lines.
     * This is due to upgrades and downgrades directly modifying the existing subscription
     * instead of creating a new one for each down/upgrade product.
     *
     * Then transforms the found invoice lines into a DTO setup in preparation for the credit action.
     * Some lines might already be credited, so those will be excluded for crediting.
     *
     * @return array<int, InvoiceToCredit>
     */
    private function getSubscriptionInvoiceLinesToCredit(
        Subscription $subscription,
        CarbonImmutable $creditFromDate,
        SubscriptionCancelReason $cancelReason,
    ): array {
        $invoicesToCredit = [];

        $invoiceLines = $this->invoiceLineRepository->getNonCreditInvoiceLinesForSubscriptionAndEndDate(
            $subscription,
            $creditFromDate,
        );

        $creditReason = $this->getCreditReasonFromCancelReason($cancelReason);

        foreach ($invoiceLines as $invoiceLine) {
            $remainder = $this->getInvoiceLineRemainder($invoiceLine, $creditFromDate, $cancelReason);
            if ($remainder !== 0) {
                $invoicesToCredit[] = new InvoiceToCredit(
                    invoice: $invoiceLine,
                    amountToCredit: $remainder,
                    creditStartDate: $this->getCreditInvoiceStartDate($invoiceLine, $creditFromDate, $cancelReason),
                    creditReason: $creditReason,
                );
            }
        }

        return $invoicesToCredit;
    }

    private function getCreditReasonFromCancelReason(SubscriptionCancelReason $cancelReason): InvoiceLineCreditReason
    {
        return match ($cancelReason) {
            SubscriptionCancelReason::REASON_ABUSE => InvoiceLineCreditReason::REASON_ABUSE,
            default => InvoiceLineCreditReason::REASON_CANCELLATION,
        };
    }

    private function getCreditInvoiceStartDate(
        Invoice $invoiceLine,
        CarbonImmutable $cancellationEndDate,
        SubscriptionCancelReason $cancelReason,
    ): CarbonImmutable {
        // in case the reason enforces a full credit, the original start date should also be used for the credit invoice
        if ($cancelReason->enforcesToCreditFully()) {
            return $invoiceLine->start_date;
        }

        if ($cancelReason->enforcesToCancelImmediately()) {
            return CarbonImmutable::now();
        }

        // Make sure the credit invoice line doesn't get a start date before the original invoice line.
        return $cancellationEndDate->max($invoiceLine->start_date);
    }

    /**
     * This is the "fun" part. We don't know if the invoice line has already been credited until we trace it back.
     * To make things even more "exciting"; invoice lines can be credited partially,
     * so there may be multiple credit invoice lines for one single invoice line.
     */
    private function getInvoiceLineRemainder(
        Invoice $invoiceLine,
        CarbonImmutable $fromDate,
        SubscriptionCancelReason $cancelReason,
    ): int {
        $creditInvoiceLines = $invoiceLine->childInvoices()->where('net_price', '<', 0)->get()->all();
        // Subtract past applied credits and discounts.
        $remainder = array_reduce(
            $creditInvoiceLines,
            // Credit prices are negative, so we add instead.
            fn (int $remainder, Invoice $creditInvoiceLine): int => $remainder + $creditInvoiceLine->net_price,
            $invoiceLine->net_price,
        );

        if ($cancelReason->enforcesToCreditFully()) {
            return $remainder;
        }

        // If the invoice line starts after the "from date", the entire invoice line remainder should be credited.
        if ($fromDate < $invoiceLine->start_date) {
            return $remainder;
        }

        // Customer may already have used their subscription for X days; credit the remainder.
        $endDays = (int) $invoiceLine->end_date->diffInDays($invoiceLine->start_date, true);

        if ($endDays === 0) {
            return $remainder;
        }

        $fromDays = (int) $fromDate->diffInDays($invoiceLine->start_date, true);
        $percentageToCredit = (($endDays - $fromDays) / $endDays) * 100;

        return (int) round($remainder * 0.01 * $percentageToCredit);
    }

    private function getCreditFromDate(Cancellation $cancellation, Subscription $subscription): CarbonImmutable
    {
        /* Take in account the fact that we renew (and bill) each subscription X days ahead of the end date.
         * This let to the decision when cancelling a renewal but is requested before the
         * actual renew date has passed, we only want to credit from the actual renewal date.
         * See: https://yh-jira.atlassian.net/browse/SWD-166
         */
        $cancelEndDate = $cancellation->getCancellationEndDate($subscription);

        /* As we don't have easy/clear history on subscriptions, we construct the last renewal date
         * by subtracting the contract period from the current end date.
         */
        $renewedDate = $subscription->end_date->subMonths($subscription->contract_period);
        if (
            $cancellation->getCancelReason() === SubscriptionCancelReason::REASON_CANCELLATION_RENEWAL
            && $cancelEndDate < $renewedDate
        ) {
            return $renewedDate;
        }

        return $cancelEndDate;
    }
}
