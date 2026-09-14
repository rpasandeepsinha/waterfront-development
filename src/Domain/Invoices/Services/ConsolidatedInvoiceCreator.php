<?php

declare(strict_types=1);

namespace Waterfront\Domain\Invoices\Services;

use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Bus\Dispatcher;
use Psr\Log\LoggerInterface;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Ferry\Repositories\MigrationCustomerRepository;
use Waterfront\Domain\Invoices\Actions\CreateInvoiceAndSetNextBillingDateForSubscriptionAction;
use Waterfront\Domain\Invoices\Jobs\DispatchConsolidatedInvoicesForCustomer;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Domain\Products\Enums\ProductPriceType;
use Waterfront\Domain\Subscriptions\Actions\IsSubscriptionPeriodEntirelyInvoicedAction;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Infra\Common\DateTimeFormat;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Support\Enums\LoggingContextKeys;

class ConsolidatedInvoiceCreator
{
    private readonly int $consolidatingDays;

    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly LoggerInterface $logger,
        private readonly CreateInvoiceAndSetNextBillingDateForSubscriptionAction $createInvoiceAndBillingDateForSubscriptionAction,
        private readonly IsSubscriptionPeriodEntirelyInvoicedAction $isSubscriptionPeriodEntirelyInvoiced,
        private readonly Dispatcher $bus,
        private readonly AdministrationFeesManager $administrationFeesManager,
        private readonly MigrationCustomerRepository $migrationCustomerRepository,
        ConfigurationInterface $configuration,
        private readonly ComesWithFreeProductInvoiceManager $comesWithFreeProductInvoiceManager,
    ) {
        $this->consolidatingDays = $configuration->getAsInteger('constants.invoice-consolidating-days');
    }

    public function invoiceCustomer(Customer $customer, DateTimeInterface $billingDate): void
    {
        $this->logger->info(
            sprintf(
                'Trying to invoicing customer %s for billing date %s',
                $customer->customer_number,
                $billingDate->format(DateTimeFormat::DATE),
            ),
            [
                LoggingContextKeys::CUSTOMER_ID => $customer->id,
                LoggingContextKeys::CUSTOMER_NUMBER => $customer->customer_number,
            ],
        );

        if (! $this->isCustomerCurrentlyValidForConsolidation($customer, $billingDate)) {
            $this->logger->info(
                sprintf(
                    'Customer with %s has no valid subscriptions for consolidation for the given billing-date %s ',
                    $customer->customer_number,
                    $billingDate->format(DateTimeFormat::DATE),
                ),
            );

            return;
        }

        $subscriptions = $this->subscriptionRepository->getAllDueForInvoicing(
            $customer,
            DateTimeImmutable::createFromInterface($billingDate)->modify(sprintf(
                '+ %d days',
                $this->consolidatingDays,
            )),
        );

        $invoicingPostponedByMigration = $this->migrationCustomerRepository->isCustomerInActiveMigrationWithInvoicingDisabled($customer->id);
        /*
         * Note:
         * In case of migration, the invoices are indeed also created
         * However the dispatching to harbor process differs as that follows a certain harbor propagator and arbiter
         * process which result actually in NOT dispatching them to harbor (yet) and that is triggered by the InvoiceCreated event
         *
         * When not in the migration, the InvoiceCreated event is NOT triggered and different route is taken
         * to make sure the invoices are INDEED dispatched, but consolidated in one message
         */
        $dispatchInvoiceCreated = $invoicingPostponedByMigration;

        $newInvoicesForCustomer = [];
        foreach ($subscriptions as $subscription) {
            if (! $this->isInvoiceableSubscription($subscription)) {
                continue;
            }

            $invoices = $this->createNewInvoicesForSubscription(
                subscription: $subscription,
                dispatchInvoiceCreated: $dispatchInvoiceCreated,
            );
            $newInvoicesForCustomer = array_merge($newInvoicesForCustomer, $invoices);
        }

        if (count($newInvoicesForCustomer) === 0) {
            $this->logger->debug(sprintf('No invoices were created for customer %d', $customer->id));

            return;
        }

        if ($this->administrationFeesManager->shouldBeChargedWithDailyBilling($customer)) {
            $netPriceSummed = 0;
            foreach ($newInvoicesForCustomer as $invoice) {
                $netPriceSummed += $invoice->net_price;
            }

            if ($netPriceSummed > 0) {
                $administrationFees = $this->administrationFeesManager->getAdministrationFees($customer);

                if ($administrationFees !== null) {
                    $newInvoicesForCustomer[] = $this->administrationFeesManager->createAdministrationFeesInvoice(
                        customer: $customer,
                        administrationFees: $administrationFees,
                        dispatchInvoiceCreated: $dispatchInvoiceCreated,
                    );
                }
            }
        }

        if (! $invoicingPostponedByMigration) {
            $this->logger->info(sprintf(
                'Dispatching consolidated invoices job for customer %d',
                $customer->id,
            ));

            $this->bus->dispatch(
                new DispatchConsolidatedInvoicesForCustomer(
                    customer: $customer,
                    invoices: $newInvoicesForCustomer,
                    createInvoiceInstantly: true,
                ),
            );
        }
    }

    private function isCustomerCurrentlyValidForConsolidation(Customer $customer, DateTimeInterface $billingDate): bool
    {
        foreach ($this->subscriptionRepository->getAllDueForInvoicing($customer, $billingDate) as $subscription) {
            if ($this->isInvoiceableSubscription($subscription)) {
                return true;
            }
        }

        return false;
    }

    private function isInvoiceableSubscription(Subscription $subscription): bool
    {
        if (
            $subscription->administrative_status === AdministrativeStatus::CANCELED->value
            && $this->isSubscriptionPeriodEntirelyInvoiced->execute($subscription)
        ) {
            $this->logger->notice(
                sprintf(
                    'Canceled subscription should not be invoiced after end date, with subscription id : %d (uuid : %s)',
                    $subscription->id,
                    $subscription->uuid,
                ),
            );

            return false;
        }

        if (in_array(
            $subscription->administrative_status,
            [
                AdministrativeStatus::CANCELED->value,
                AdministrativeStatus::ACTIVE->value,
                AdministrativeStatus::SUSPENDED->value,
            ],
            true,
        )) {
            return true;
        }

        return false;
    }

    /**
     * @return Invoice[]
     */
    private function createNewInvoicesForSubscription(
        Subscription $subscription,
        bool $dispatchInvoiceCreated = true,
    ): array {
        $invoices = [];
        $this->logger->info(
            sprintf(
                'Creating invoicing for subscription %d (uuid : %s)',
                $subscription->id,
                $subscription->uuid,
            ),
        );

        $newInvoice = $this->createInvoiceAndBillingDateForSubscriptionAction->execute(
            $subscription,
            $dispatchInvoiceCreated,
        );
        $invoices[] = $newInvoice;

        if ($this->comesWithFreeProductInvoiceManager->isSubscriptionWhichComesWithFreeProduct($subscription)) {
            $freeInvoice = $this->comesWithFreeProductInvoiceManager->createInvoice(
                subscription: $subscription,
                paidInvoice: $newInvoice,
                dispatchInvoiceCreated: $dispatchInvoiceCreated,
                priceType: ProductPriceType::PROLONGATION,
            );
            if ($freeInvoice instanceof Invoice) {
                $invoices[] = $freeInvoice;
            }
        }

        $children = Subscription::query()
            ->where('parent_subscription_id', $subscription->id)
            ->whereNotIn('administrative_status', [
                ...AdministrativeStatus::administrativelyEnded(),
                AdministrativeStatus::ARCHIVING->value,
            ])
            ->cursor();

        $children->each(
            function ($childSubscription) use ($dispatchInvoiceCreated, &$invoices): void {
                if (! $this->isInvoiceableSubscription($childSubscription)) {
                    return;
                }

                $invoices[] = $this->createInvoiceAndBillingDateForSubscriptionAction->execute(
                    $childSubscription,
                    $dispatchInvoiceCreated,
                );
            },
        );

        return $invoices;
    }
}
