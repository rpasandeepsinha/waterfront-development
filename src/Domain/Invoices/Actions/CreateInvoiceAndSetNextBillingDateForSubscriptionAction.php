<?php

declare(strict_types=1);

namespace Waterfront\Domain\Invoices\Actions;

use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Domain\Invoices\Repositories\InvoiceRepository;
use Waterfront\Domain\Pricing\Models\SubscriptionPrice;
use Waterfront\Domain\Subscriptions\Actions\GetNextInvoicePriceAction;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class CreateInvoiceAndSetNextBillingDateForSubscriptionAction
{
    public function __construct(
        private readonly InvoiceRepository $invoiceRepository,
        private readonly GetNextInvoicePriceAction $getNextInvoicePriceAction,
    ) {
    }

    public function execute(Subscription $subscription, bool $dispatchInvoiceCreated = true): Invoice
    {
        $nextInvoicePrice = $this->getNextInvoicePriceAction->execute($subscription);

        $invoice = $this->invoiceRepository->createNextSubscriptionInvoice(
            $subscription,
            $nextInvoicePrice,
            $dispatchInvoiceCreated
        );

        if ($nextInvoicePrice->nextPrice instanceof SubscriptionPrice) {
            $subscription->subscription_price_id = $nextInvoicePrice->nextPrice->id;
            $subscription->net_price = $nextInvoicePrice->netPrice;
        }

        $subscription->next_billing_date = $nextInvoicePrice->endDate;
        $subscription->save();

        return $invoice;
    }
}
