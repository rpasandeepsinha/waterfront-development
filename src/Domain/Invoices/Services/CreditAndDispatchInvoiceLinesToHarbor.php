<?php

declare(strict_types=1);

namespace Waterfront\Domain\Invoices\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Psr\Log\LoggerInterface;
use SandwaveIo\HarborMessages\Message\DebtorInvoiceLines;
use Waterfront\Domain\Harbor\DTO\Message\InvoiceLineMessageConfig;
use Waterfront\Domain\Harbor\DTO\Services\Invoice\InvoiceBatchCrediter\BatchInvoiceCreditResult;
use Waterfront\Domain\Harbor\DTO\Services\Invoice\InvoiceBatchCrediter\InvoiceToCreditBatch;
use Waterfront\Domain\Harbor\DTO\Services\Invoice\InvoiceCrediter\InvoiceToCredit;
use Waterfront\Domain\Harbor\Exceptions\HarborApiResponseException;
use Waterfront\Domain\Harbor\Exceptions\InvoiceLineToHarborException;
use Waterfront\Domain\Harbor\Services\HarborApiClient\HarborApi;
use Waterfront\Domain\Harbor\Services\Invoice\InvoiceBatchCrediter;
use Waterfront\Domain\Harbor\Services\Invoice\InvoiceCrediter;
use Waterfront\Domain\Harbor\Services\Invoice\InvoiceModifier;
use Waterfront\Domain\Harbor\Services\Message\MessageService;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Domain\Subscriptions\Exceptions\Services\SubscriptionCrediter\MissingParentInvoiceLineException;
use Waterfront\Domain\Subscriptions\Exceptions\Services\SubscriptionCrediter\MultipleCustomersException;
use Waterfront\Domain\Subscriptions\Exceptions\Services\SubscriptionCrediter\SubscriptionCrediterException;
use Waterfront\Domain\Subscriptions\Exceptions\Services\SubscriptionCrediter\UnsentInvoiceLineException;
use Waterfront\Support\Enums\LoggingContextKeys;

/**
 * Entry point for crediting the invoice lines in the context of one or multiple subscriptions FOR ONE CUSTOMER.
 * The credit action is also immediately communicated to Harbor for you (synchronously),
 * meaning this service's functionalities are fire-and-forget.
 *
 * Currently, this only supports a FULL credit of the subscription.
 * If you wish to partially credit, for now you should directly use one of the "Invoice...Crediter" services instead,
 * or optimally, expand upon this service when you have time (using the existing crediters as reference).
 *
 * @see InvoiceCrediter for crediting in the context of one invoice line.
 * @see InvoiceBatchCrediter for crediting in the context of multiple invoice lines (to be moved to InvoiceCrediter).
 */
class CreditAndDispatchInvoiceLinesToHarbor
{
    public function __construct(
        private readonly InvoiceBatchCrediter $batchCrediter,
        private readonly InvoiceModifier $invoiceLineModifier,
        private readonly MessageService $messageBuilder,
        private readonly HarborApi $harborApi,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Handle the given invoices to credit batch. This will:
     * - Perform some basic checks; must be for single customer, invoice lines to credit must be already sent to Harbor.
     * - Create the credit lines for each item in the batch.
     * - Send an API call to Harbor to instantly create a credit invoice with these line.
     *
     * @throws UnsentInvoiceLineException        When one of the invoice lines has not yet been sent to Harbor before crediting.
     * @throws MultipleCustomersException        When one of the invoice lines is of a customer other than one previously found in the subscriptions.
     * @throws MissingParentInvoiceLineException When one of the invoice lines has no attached parent invoice line after crediting.
     * @throws SubscriptionCrediterException
     * @throws HarborApiResponseException
     */
    public function creditAndDispatch(InvoiceToCreditBatch $invoiceLinesToCredit): void
    {
        if ($invoiceLinesToCredit->count() === 0) {
            $this->logger->error('No invoice lines given that should be credited.');
            return;
        }

        $invoiceLineIds = array_map(
            fn (InvoiceToCredit $invoiceToCredit) => $invoiceToCredit->getInvoice()->id,
            $invoiceLinesToCredit->getInvoicesToCredit()
        );
        $this->logger->notice(
            'Trying to credit and dispatch invoices lines',
            [
                LoggingContextKeys::INVOICE_LINE_ID => $invoiceLineIds,
            ],
        );

        $this->validateCreditForSingleCustomer($invoiceLinesToCredit);
        $this->validateSentToHarbor($invoiceLinesToCredit);

        DB::transaction(function () use (
            &$invoiceLinesToCredit,
        ) {
            $this->logger->debug('Batch crediting the given invoice lines');
            $creditResult = $this->batchCrediter->batchCredit($invoiceLinesToCredit);

            // ...Because they are to be immediately sent, instead of through the usual queue.
            $this->invoiceLineModifier->bulkUpdate($creditResult->getCreditInvoices(), [
                'sent_to_harbor_at' => CarbonImmutable::now(),
            ]);

            $message = $this->buildMessage($creditResult);

            $this->logger->info('Sending credited invoice lines to harbor');
            $this->harborApi->sendCredit($message);
        });
    }

    /**
     * @see MultipleCustomersException Contains the explanation for why we perform this check.
     *
     * @throws MultipleCustomersException
     * @throws SubscriptionCrediterException
     */
    private function validateCreditForSingleCustomer(InvoiceToCreditBatch $invoiceToCreditBatch): void
    {
        $customerId = null;
        foreach ($invoiceToCreditBatch->getInvoicesToCredit() as $invoiceToCredit) {
            $customerId ??= $invoiceToCredit->getInvoice()->customer_id;

            if ($invoiceToCredit->getInvoice()->customer_id !== $customerId) {
                $this->logAndThrowException(new MultipleCustomersException($invoiceToCredit->getInvoice(), $customerId, self::class));
            }
        }
    }

    /**
     * @throws SubscriptionCrediterException
     */
    private function validateSentToHarbor(InvoiceToCreditBatch $invoiceToCreditBatch): void
    {
        foreach ($invoiceToCreditBatch->getInvoicesToCredit() as $invoiceToCredit) {
            if ($invoiceToCredit->getInvoice()->sent_to_harbor_at === null) {
                $this->logAndThrowException(new UnsentInvoiceLineException($invoiceToCredit->getInvoice(), self::class));
            }
        }
    }

    /**
     * @throws SubscriptionCrediterException
     * @throws MissingParentInvoiceLineException
     * @throws InvoiceLineToHarborException
     */
    private function buildMessage(BatchInvoiceCreditResult $creditResult): DebtorInvoiceLines
    {
        $creditInvoiceLines = $creditResult->getCreditInvoices();
        $customer = $creditInvoiceLines[0]->customer;

        return $this->messageBuilder->build(
            $customer,
            $creditInvoiceLines,
            array_map(function (Invoice $invoiceLine): InvoiceLineMessageConfig {
                $parentInvoiceLine = $invoiceLine->parentInvoice;

                /**
                 * Since this cannot ever occur so long as the InvoiceCrediter service sets parent_invoice_id the way it does now,
                 * this logically cannot ever occur either.
                 * I decided to include it like this anyway for formality's sake,
                 * so that this case would be covered in the event that the aforementioned service is changed.
                 * This means this case also does not appear in the integration test.
                 *
                 * @see InvoiceCrediter::credit()
                 */
                if ($parentInvoiceLine === null) {
                    $this->logAndThrowException(new MissingParentInvoiceLineException($invoiceLine, self::class));
                }

                $subscription = $invoiceLine->subscription;
                $product = $invoiceLine->product;

                return new InvoiceLineMessageConfig(
                    invoice: $invoiceLine,
                    product: $product,
                    subscription: $subscription,
                    creditedInvoiceId: $parentInvoiceLine->id,
                );
            }, $creditInvoiceLines),
        );
    }

    /**
     * @throws SubscriptionCrediterException
     */
    private function logAndThrowException(SubscriptionCrediterException $exception): never
    {
        $this->logger->error($exception->getMessage(), [
            LoggingContextKeys::EXCEPTION => $exception,
        ]);

        throw $exception;
    }
}
