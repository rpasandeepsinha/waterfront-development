<?php

declare(strict_types=1);

namespace Waterfront\Domain\Harbor\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use SandwaveIo\HarborMessages\Message\Enum\InvoiceLineCreditReason;
use Waterfront\Domain\Harbor\Exceptions\HarborApiResponseException;
use Waterfront\Domain\Harbor\Services\HarborApiClient\HarborApi;
use Waterfront\Domain\Harbor\Services\Invoice\InvoiceSingleCrediter;
use Waterfront\Domain\Invoices\Repositories\InvoiceRepository;
use Waterfront\Domain\Subscriptions\Enums\ProductChangeType;
use Waterfront\Domain\Subscriptions\Events\SubscriptionChangedEvent;
use Waterfront\Support\Enums\QueueName;

class SendCreditInvoiceListener implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = QueueName::INVOICES->value;

    /**
     * Create the event listener.
     */
    public function __construct(
        private readonly InvoiceRepository $invoiceRepository,
        private readonly InvoiceSingleCrediter $invoiceSingleCrediter,
        private readonly HarborApi $harborApi,
    ) {
    }

    /**
     * Handle the event.
     */
    public function handle(SubscriptionChangedEvent $event): void
    {
        if ($event->changeType !== ProductChangeType::DOWNGRADE) {
            Log::info(
                sprintf(
                    'Subscription with id : %d (uuid : %s) has been modified but does not need to be credited as it is not a downgrade',
                    $event->subscription->id,
                    $event->subscription->uuid,
                ),
            );

            return;
        }

        $downgradeInvoiceLine = $this->invoiceRepository->getInvoiceLineForDowngradedSubscription($event->subscription);

        if ($downgradeInvoiceLine === null) {
            Log::error(
                sprintf(
                    'Subscription with id : %d (uuid : %s) has no invoice line for the new product : %s',
                    $event->subscription->id,
                    $event->subscription->uuid,
                    sprintf('%s (%s)', $event->subscription->product->name, ProductChangeType::DOWNGRADE->value),
                ),
            );

            return;
        }

        $invoiceLineToCredit = $this->invoiceRepository->getLatestPaidInvoiceLineForDowngradedSubscription($event->subscription);

        if ($invoiceLineToCredit === null) {
            Log::error(
                sprintf(
                    'Subscription with id : %d (uuid : %s) has no invoice line that has paid, so there is nothing to credit',
                    $event->subscription->id,
                    $event->subscription->uuid,
                ),
            );

            return;
        }

        //There is a invoice to credit, so credit this and let send it to Harbor
        $invoiceMessages = $this->invoiceSingleCrediter->creditInvoice(
            originalInvoiceLine: $invoiceLineToCredit,
            newInvoiceLine: $downgradeInvoiceLine,
            creditReason: InvoiceLineCreditReason::REASON_DOWNGRADE,
        );

        try {
            $this->harborApi->sendDowngrade(
                originalInvoiceId: $invoiceLineToCredit->id,
                creditInvoiceLineMessage: $invoiceMessages['creditInvoiceLineMessage'],
                newInvoiceLineMessage: $invoiceMessages['newInvoiceLineMessage'],
            );

            /** @var array<array<string, mixed>> $createdCreditInvoice */
            $createdCreditInvoice = $invoiceMessages['creditInvoiceLineMessage']->getInvoiceLines()->toArray();
            /** @var int $invoiceId */
            $invoiceId = $createdCreditInvoice[0]['waterfront_invoice_id'];
            $updated = $this->invoiceRepository->setIsSentToHarbor($invoiceId);
            $updatedDowngradedInvoice = $this->invoiceRepository->setIsSentToHarbor($downgradeInvoiceLine->id);

            if ($updated !== 1) {
                Log::error(
                    sprintf(
                        'Subscription with id : %d (uuid : %s) was send, but InvoiceLine(credit) with Id %d is not updated correctly',
                        $event->subscription->id,
                        $event->subscription->uuid,
                        $invoiceId,
                    ),
                );

                return;
            }

            if ($updatedDowngradedInvoice !== 1) {
                Log::error(
                    sprintf(
                        'Subscription with id : %d (uuid : %s) was send, but InvoiceLine(downgraded) with Id %d is not updated correctly',
                        $event->subscription->id,
                        $event->subscription->uuid,
                        $downgradeInvoiceLine->id,
                    ),
                );

                return;
            }

            Log::info(
                sprintf(
                    'CreditInvoice for subscription with id : %d (uuid : %s) was successfully send to harbor -> creditInvoiceId : %d ',
                    $event->subscription->id,
                    $event->subscription->uuid,
                    $invoiceId,
                ),
            );
        } catch (HarborApiResponseException $exception) {
            $lockDowngradeUpdate = $this->invoiceRepository->lockInvoiceLine($downgradeInvoiceLine->id);

            /** @var array<array<string, mixed>> $createdCreditInvoice */
            $createdCreditInvoice = $invoiceMessages['creditInvoiceLineMessage']->getInvoiceLines()->toArray();
            /** @var int $invoiceId */
            $invoiceId = $createdCreditInvoice[0]['waterfront_invoice_id'];
            $lockCreditUpdate = $this->invoiceRepository->lockInvoiceLine($invoiceId);

            Log::error(sprintf(
                'CreditInvoice for subscription with id : %d (uuid : %s) could not send to Harbor due an api error : %s',
                $event->subscription->id,
                $event->subscription->uuid,
                $exception->getMessage(),
            ));

            if ($lockDowngradeUpdate === 1) {
                Log::info(sprintf(
                    'Downgrade Invoice for subscription with id : %d (uuid : %s) is locket InvoiceId : %d',
                    $event->subscription->id,
                    $event->subscription->uuid,
                    $downgradeInvoiceLine->id,
                ));
            }

            if ($lockCreditUpdate === 1) {
                Log::info(sprintf(
                    'CreditInvoice for subscription with id : %d (uuid : %s) is locket InvoiceId : %d',
                    $event->subscription->id,
                    $event->subscription->uuid,
                    $invoiceId,
                ));
            }
        }
    }
}
