<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\DTO;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelReason;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelType;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Webmozart\Assert\Assert;

class Cancellation
{
    private readonly ?CarbonImmutable $selectedCancelEndDate;

    /**
     * @param Collection<int, Subscription> $subscriptions
     */
    public function __construct(
        private readonly Collection $subscriptions,
        private readonly SubscriptionCancelReason $cancelReason,
        private readonly ?string $cancelReasonOther,
        private readonly SubscriptionCancelType $cancelType,
        ?CarbonImmutable $selectedCancelEndDate,
        private readonly bool $creditRelatedInvoices
    ) {
        // Lets interpreted types DIRECT and OTHER as the same -> cancel on specific given selected date

        Assert::notNull($subscriptions->first());

        if ($this->cancelType !== SubscriptionCancelType::CANCEL_END_DATE) {
            Assert::notNull(
                $selectedCancelEndDate,
                'A selected end date must be given.'
            );
        }

        $this->selectedCancelEndDate = $selectedCancelEndDate;
    }

    /**
     * @return Collection<int, Subscription>
     */
    public function getSubscriptions(): Collection
    {
        return $this->subscriptions;
    }

    public function getCancelReason(): SubscriptionCancelReason
    {
        return $this->cancelReason;
    }

    public function getCancelReasonOther(): ?string
    {
        if ($this->cancelReason !== SubscriptionCancelReason::REASON_OTHER) {
            return null;
        }

        return $this->cancelReasonOther;
    }

    public function getCancelType(): SubscriptionCancelType
    {
        return $this->cancelType;
    }

    public function getSelectedCancellationEndDate(): ?CarbonImmutable
    {
        return $this->selectedCancelEndDate;
    }

    public function getCancellationEndDate(Subscription $subscription): CarbonImmutable
    {
        if ($this->cancelType === SubscriptionCancelType::CANCEL_END_DATE) {
            return $subscription->end_date;
        }

        // Because of the check in the constructor we know that a selected date must be set here
        assert($this->selectedCancelEndDate !== null);

        /* Setting the end after current renewal would break life cycle of the subscription,
         * as no renewal will take place while it actually still should. */
        if ($this->selectedCancelEndDate > $subscription->end_date) {
            return $subscription->end_date;
        }

        return $this->selectedCancelEndDate;
    }

    public function shouldCreditRelatedInvoices(): bool
    {
        return $this->creditRelatedInvoices;
    }
}
