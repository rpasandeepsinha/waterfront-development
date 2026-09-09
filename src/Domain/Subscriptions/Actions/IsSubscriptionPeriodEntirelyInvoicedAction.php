<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Actions;

use Waterfront\Domain\Subscriptions\Models\Subscription;

class IsSubscriptionPeriodEntirelyInvoicedAction
{
    public function execute(Subscription $subscription): bool
    {
        return $subscription->next_billing_date >= $subscription->end_date;
    }
}
