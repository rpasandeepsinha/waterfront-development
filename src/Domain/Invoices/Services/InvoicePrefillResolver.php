<?php

declare(strict_types=1);

namespace Waterfront\Domain\Invoices\Services;

use Waterfront\Domain\Invoices\DTO\InvoicePrefillDTO;
use Waterfront\Domain\Invoices\Repositories\InvoiceRepository;
use Waterfront\Domain\Subscriptions\Models\Subscription;

readonly class InvoicePrefillResolver
{
    public function __construct(
        private InvoiceRepository $invoiceRepository,
    ) {
    }

    public function resolveForSubscription(Subscription $subscription): InvoicePrefillDTO
    {
        $subscription->loadMissing('product');
        $lastInvoice = $this->invoiceRepository->getLastDebitInvoiceForSubscription($subscription);

        if ($lastInvoice === null) {
            return new InvoicePrefillDTO(null, null, null, null, $subscription->product);
        }

        $periodMatches = $subscription->billing_period === $lastInvoice->period;
        $startDate = $lastInvoice->start_date;
        $endDate = $periodMatches ? $lastInvoice->end_date : $startDate->addMonths($subscription->billing_period);
        $reusePrices = $periodMatches && $subscription->product->id === $lastInvoice->product->id;

        return new InvoicePrefillDTO(
            startDate: $startDate,
            endDate: $endDate,
            grossPrice: $reusePrices ? $lastInvoice->gross_price : null,
            netPrice: $reusePrices ? $lastInvoice->net_price : null,
            product: $subscription->product,
        );
    }
}
