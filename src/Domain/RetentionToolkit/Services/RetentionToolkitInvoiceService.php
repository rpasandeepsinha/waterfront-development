<?php

declare(strict_types=1);

namespace Waterfront\Domain\RetentionToolkit\Services;

use Carbon\CarbonImmutable;
use Waterfront\Domain\Invoices\Actions\CreateInvoiceAndSetNextBillingDateForSubscriptionAction;
use Waterfront\Domain\Subscriptions\Models\Subscription;

readonly class RetentionToolkitInvoiceService
{
    public function __construct(
        private CreateInvoiceAndSetNextBillingDateForSubscriptionAction $createInvoiceAction,
    ) {
    }

    public function createInvoiceFromEffectiveDate(
        Subscription $subscription,
        CarbonImmutable $effectiveDate,
    ): void {
        $subscription->next_billing_date = $effectiveDate;
        $subscription->save();

        $this->createInvoiceAction->execute($subscription);
    }
}
