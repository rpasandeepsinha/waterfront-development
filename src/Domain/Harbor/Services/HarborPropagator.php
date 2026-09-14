<?php

declare(strict_types=1);

namespace Waterfront\Domain\Harbor\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Waterfront\Domain\Harbor\Exceptions\InvoiceLineToHarborException;
use Waterfront\Domain\Harbor\Interfaces\CommunicatesWithHarbor;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Domain\OneTimeServices\Models\OneTimeService;
use Waterfront\Domain\Orders\Models\OrderLineItem;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class HarborPropagator
{
    public function __construct(
        private readonly CommunicatesWithHarbor $harbor,
    ) {
    }

    public function propagate(Invoice $invoice): void
    {
        $subscription = $invoice->subscription;

        if (! $subscription instanceof Subscription) {
            $this->harbor->propagateInvoice($invoice);

            return;
        }

        /** @var OrderLineItem|null $orderLineItem */
        $orderLineItem = $subscription->orderLineItem;

        if ($orderLineItem === null) {
            $this->harbor->propagateInvoice($invoice);

            return;
        }

        $order = $orderLineItem->order;
        $order->loadMissing('lineItems.subscription', 'lineItems.order', 'lineItems.oneTimeService');
        $subscriptions = new Collection();
        $oneTimeServices = new Collection();

        try {
            $order->lineItems->each(function (OrderLineItem $item) use (
                $subscriptions,
                $oneTimeServices,
                $invoice,
            ): void {
                $subscription = $item->subscription;
                $oneTimeService = $item->oneTimeService;

                if ($subscription === null && $oneTimeService === null) {
                    throw InvoiceLineToHarborException::incompleteOrderException(
                        $item->id,
                        $item->domain ?? $item->product_name . ' ' . $item->order->customer_id,
                        $invoice->id,
                    );
                }

                if ($subscription instanceof Subscription) {
                    $subscriptions->add($subscription);
                }

                if ($oneTimeService instanceof OneTimeService) {
                    $oneTimeServices->add($oneTimeService);
                }
            });
        } catch (InvoiceLineToHarborException $exception) {
            Log::error($exception->getMessage());

            return;
        }

        $subscriptions->each(function (Subscription $subscription): void {
            foreach ($subscription->invoices()->whereNull('sent_to_harbor_at')->get() as $invoice) {
                if ($invoice instanceof Invoice) {
                    $this->harbor->propagateInvoice($invoice);
                }
            }
        });

        $oneTimeServices->each(function (OneTimeService $oneTimeService): void {
            foreach ($oneTimeService->invoices()->whereNull('sent_to_harbor_at')->get() as $invoice) {
                if ($invoice instanceof Invoice) {
                    $this->harbor->propagateInvoice($invoice);
                }
            }
        });
    }
}
