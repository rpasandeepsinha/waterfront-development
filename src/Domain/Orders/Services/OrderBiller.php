<?php

declare(strict_types=1);

namespace Waterfront\Domain\Orders\Services;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Facades\DB;
use Psr\Log\LoggerInterface;
use Throwable;
use Waterfront\Domain\Invoices\DTO\AdministrationFees;
use Waterfront\Domain\Invoices\Jobs\DispatchConsolidatedInvoicesForCustomer;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Domain\Invoices\Repositories\InvoiceRepository;
use Waterfront\Domain\Invoices\Services\AdministrationFeesManager;
use Waterfront\Domain\Invoices\Services\ComesWithFreeProductInvoiceManager;
use Waterfront\Domain\OneTimeServices\Models\OneTimeService;
use Waterfront\Domain\OneTimeServices\Services\OneTimeServiceInvoiceService;
use Waterfront\Domain\Orders\Enums\OrderStatus;
use Waterfront\Domain\Orders\Exceptions\IncompleteOrderLineException;
use Waterfront\Domain\Orders\Exceptions\OrderAlreadyInvoicedException;
use Waterfront\Domain\Orders\Exceptions\OrderNotProcessedException;
use Waterfront\Domain\Orders\Models\Order;
use Waterfront\Domain\Orders\Models\OrderLineItem;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

class OrderBiller
{
    public function __construct(
        private readonly OneTimeServiceInvoiceService $oneTimeServiceInvoiceService,
        private readonly LoggerInterface $logger,
        private readonly InvoiceRepository $invoiceRepository,
        private readonly Dispatcher $dispatcher,
        private readonly AdministrationFeesManager $administrationFeesManager,
        private readonly ComesWithFreeProductInvoiceManager $comesWithFreeProductInvoiceManager,
    ) {
    }

    public function bill(Order $order): void
    {
        $order->loadMissing(
            [
                'lineItems.product',
                'lineItems.subscription',
                'lineItems.subscription.product',
                'lineItems.product.productGroup',
                'lineItems.product.productSpecs',
                'lineItems.oneTimeService',
                'lineItems.oneTimeService.subscription',
                'lineItems.oneTimeService.subscription.customer',
                'lineItems.oneTimeService.product',
            ],
        );

        $this->logger->info(
            'Start billing of order {order.id}',
            [
                LoggingContextKeys::ORDER_ID => $order->id,
                LoggingContextKeys::CUSTOMER_ID => $order->customer->id,
            ],
        );

        $this->validateOrderIsInBillableState($order);
        $billableLineItems = $this->getValidatedBillableItems($order);

        if (count($billableLineItems) === 0) {
            $order->is_invoiced = true;
            $order->save();

            return;
        }

        try {
            $invoices = [];

            $prepaidReference = $order->getLatestPaidPayment()?->external_id;

            DB::beginTransaction();

            foreach ($billableLineItems as $lineItem) {
                if ($lineItem->product?->productGroup->slug === ProductGroupType::ONE_TIME_SERVICE) {
                    assert($lineItem->oneTimeService instanceof OneTimeService);
                    $invoicesOts = $this->oneTimeServiceInvoiceService->createOneTimeServiceInvoices(
                        $lineItem->oneTimeService,
                        $order->isPaid(),
                    );
                    $invoices = array_merge($invoices, $invoicesOts);
                    continue;
                }

                $newInvoice = $this->invoiceRepository->createOrderLineSubscriptionInvoice(
                    $order->customer,
                    $lineItem,
                    $prepaidReference,
                );

                if (
                    $lineItem->subscription instanceof Subscription
                    && $this->comesWithFreeProductInvoiceManager->isSubscriptionWhichComesWithFreeProduct($lineItem->subscription)
                ) {
                    $freeInvoice = $this->comesWithFreeProductInvoiceManager->createInvoice(
                        subscription: $lineItem->subscription,
                        paidInvoice: $newInvoice,
                        prepaidReference: $prepaidReference,
                        dispatchInvoiceCreated: false,
                    );
                    if ($freeInvoice instanceof Invoice) {
                        $invoices[] = $freeInvoice;
                    }
                }

                $invoices[] = $newInvoice;
            }

            if ($order->administration_fees !== 0) {
                // Somehow the customer has ordered and (most likely) paid something that includes admin fees
                // therefor a result of an actual 'AdministrationFees' from 'getAdministrationFees()' is expected
                $administrationFees = $this->administrationFeesManager->getAdministrationFees($order->customer);
                Assert::isInstanceOf($administrationFees, AdministrationFees::class);
                $invoices[] = $this->administrationFeesManager->createAdministrationFeesInvoice(
                    customer: $order->customer,
                    administrationFees: $administrationFees,
                    administrationFeesPrice: $order->administration_fees,
                    prepaidReference: $prepaidReference,
                    dispatchInvoiceCreated: false,
                );
            }

            DB::commit();

            $this->logger->info(
                sprintf('Order {order.id} billed with %s invoice lines', count($invoices)),
                [
                    LoggingContextKeys::ORDER_ID => $order->id,
                    LoggingContextKeys::CUSTOMER_ID => $order->customer->id,
                ],
            );
        } catch (Throwable $exception) {
            DB::rollBack();

            $this->logger->error(
                'Billing order failed, rollback executed for order {order.id}.',
                [
                    LoggingContextKeys::ORDER_ID => $order->id,
                    LoggingContextKeys::EXCEPTION => $exception,
                ],
            );
            throw $exception;
        }

        $order->is_invoiced = true;
        $order->save();

        $this->dispatcher->dispatch(
            new DispatchConsolidatedInvoicesForCustomer(
                customer: $order->customer,
                invoices: $invoices,
                createInvoiceInstantly: true,
            ),
        );
    }

    private function validateOrderIsInBillableState(Order $order): void
    {
        if ($order->is_invoiced) {
            throw new OrderAlreadyInvoicedException(
                sprintf('Billing order aborted. Order %s has already been invoiced.', $order->id),
            );
        }

        if ($order->status !== OrderStatus::PROCESSED) {
            throw new OrderNotProcessedException(
                sprintf('Billing order aborted. Order %s has not yet been fully processed.', $order->id),
            );
        }
    }

    /**
     * @return array<int, OrderLineItem>
     */
    private function getValidatedBillableItems(Order $order): array
    {
        $billableItems = [];

        foreach ($order->lineItems as $lineItem) {
            if ($lineItem->should_invoice === false) {
                continue;
            }

            // If an OTS order, the OTS must be linked to the line item
            if ($lineItem->product?->productGroup->slug === ProductGroupType::ONE_TIME_SERVICE) {
                if (! $lineItem->oneTimeService instanceof OneTimeService) {
                    throw new IncompleteOrderLineException('order line missing OTS relation');
                }

                $billableItems[] = $lineItem;
                continue;
            }

            // In any other case we expect a subscription linked
            if (! $lineItem->subscription instanceof Subscription) {
                throw new IncompleteOrderLineException('order line missing subscription relation');
            }

            if ($lineItem->subscription->administrative_status === AdministrativeStatus::INACTIVE->value) {
                $this->logger->warning(
                    'Billing skipped for order line item ({order_line.id}). Subscription ({subscription.id}) administrative status is INACTIVE.',
                    [
                        LoggingContextKeys::ORDER_LINE_ID => $lineItem->id,
                        LoggingContextKeys::ORDER_ID => $order->id,
                        LoggingContextKeys::SUBSCRIPTION_ID => $lineItem->subscription->id,
                    ],
                );
                continue;
            }

            $billableItems[] = $lineItem;
        }

        return $billableItems;
    }
}
