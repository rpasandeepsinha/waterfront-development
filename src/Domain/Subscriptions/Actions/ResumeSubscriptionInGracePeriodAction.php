<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Actions;

use UnexpectedValueException;
use Waterfront\Domain\Invoices\Actions\CreateInvoiceAndSetNextBillingDateForSubscriptionAction;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Services\SubscriptionRenewService;

class ResumeSubscriptionInGracePeriodAction
{
    public function __construct(
        private readonly SaveSubscriptionAdministrativeStatusAction $saveSubscriptionAdministrativeStatusAction,
        private readonly SubscriptionRenewService $subscriptionRenewService,
        private readonly CreateInvoiceAndSetNextBillingDateForSubscriptionAction $createInvoiceAndSetNextBillingDateForSubscriptionAction,
    ) {
    }

    public function execute(Subscription $subscription): void
    {
        if ($subscription->administrative_status !== AdministrativeStatus::EXPIRED->value) {
            throw new UnexpectedValueException('Subscription does not meet the criteria to be resumed');
        }

        $this->saveSubscriptionAdministrativeStatusAction->execute(
            $subscription,
            AdministrativeStatus::ACTIVE
        );

        $subscription->termination_date = null;
        $subscription->cancel_date = null;
        $subscription->save();

        $subscription->refresh();

        $subscription->loadMissing(['product']);

        $this->createInvoiceAndSetNextBillingDateForSubscriptionAction->execute($subscription);

        $this->subscriptionRenewService->renewSubscription($subscription);
    }
}
