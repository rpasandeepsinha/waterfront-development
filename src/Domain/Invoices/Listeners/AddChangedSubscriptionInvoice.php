<?php

declare(strict_types=1);

namespace Waterfront\Domain\Invoices\Listeners;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Waterfront\Domain\Customers\Exceptions\InvalidCountryCodeException;
use Waterfront\Domain\Invoices\Events\InvoiceCreatedEvent;
use Waterfront\Domain\Invoices\Repositories\InvoiceRepository;
use Waterfront\Domain\Subscriptions\Enums\ProductChangeType;
use Waterfront\Domain\Subscriptions\Events\SubscriptionChangedEvent;
use Waterfront\Support\Enums\QueueName;

/**
 * Add an invoice for the subscription.
 */
class AddChangedSubscriptionInvoice implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = QueueName::INVOICES->value;

    public function __construct(
        private readonly InvoiceRepository $invoiceRepository,
        private readonly Dispatcher $dispatcher
    ) {
    }

    /**
     * @throws InvalidCountryCodeException
     */
    public function handle(SubscriptionChangedEvent $event): void
    {
        $invoice = $this->invoiceRepository->createInvoiceForChangedSubscription(
            subscription: $event->subscription,
            charge: $event->charge,
            changeType: $event->changeType,
            startDate: CarbonImmutable::now()
        );

        /**
         * Dispatch only the upgrade invoices, so they get send to Harbor.
         * Downgrade invoices are handled differently within `\App\Listeners\SendCreditInvoiceListener`.
         */
        if ($event->changeType === ProductChangeType::UPGRADE) {
            $this->dispatcher->dispatch(
                new InvoiceCreatedEvent($invoice, false)
            );
        }
    }
}
