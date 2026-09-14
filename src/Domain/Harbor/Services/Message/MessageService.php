<?php

declare(strict_types=1);

namespace Waterfront\Domain\Harbor\Services\Message;

use Carbon\CarbonImmutable;
use Psr\Log\LoggerInterface;
use SandwaveIo\HarborMessages\Message\DebtorInvoiceLines;
use SandwaveIo\HarborMessages\Message\DebtorInvoiceLines\InvoiceLine;
use SandwaveIo\HarborMessages\Message\DebtorInvoiceLines\InvoiceLineCollection;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Harbor\DTO\Message\InvoiceLineMessageConfig;
use Waterfront\Domain\Harbor\Exceptions\InvoiceLineToHarborException;
use Waterfront\Domain\Harbor\Services\Invoice\InvoiceModifier;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Domain\Invoices\Repositories\InvoiceRepository;
use Waterfront\Infra\Common\DateTimeFormat;
use Waterfront\Infra\Queue\Exceptions\HarborClientException;
use Waterfront\Infra\Queue\HarborQueue;
use Waterfront\Support\Enums\LoggingContextKeys;

class MessageService
{
    public function __construct(
        private readonly DebtorBuilder $debtorBuilder,
        private readonly InvoiceRepository $invoiceRepository,
        private readonly InvoiceModifier $invoiceModifier,
        private readonly HarborQueue $queue,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Builds a message and places this on the queue to Harbor.
     *
     * @param Invoice[]                  $invoices
     * @param InvoiceLineMessageConfig[] $configs
     * @param bool                       $createInvoiceInstantly Message results in 1 invoice with the attached invoice lines right away in Harbor.
     *
     * @throws InvoiceLineToHarborException
     * @throws HarborClientException
     *
     * @see MessageService::build()
     */
    public function queue(
        Customer $customer,
        array $invoices,
        array $configs = [],
        bool $createInvoiceInstantly = false,
    ): void {
        $this->logger->info(sprintf(
            'Queuing a new message for customer id %d %s invoice lines...',
            $customer->id,
            count($invoices) > 0 ? 'WITH' : 'WITHOUT',
        ), [
            LoggingContextKeys::CUSTOMER_NUMBER => $customer->customer_number,
            LoggingContextKeys::INVOICE_LINE_ID => array_map(fn (Invoice $invoice): int => $invoice->id, $invoices),
        ]);

        $this->queue->publish($this->build($customer, $invoices, $configs, $createInvoiceInstantly));
        $this->markInvoicesAsSent($invoices);

        $this->logger->info(sprintf(
            'Successfully queued a new message for customer id %d %s invoice lines!',
            $customer->id,
            count($invoices) > 0 ? 'WITH' : 'WITHOUT',
        ), [
            LoggingContextKeys::CUSTOMER_NUMBER => $customer->customer_number,
            LoggingContextKeys::INVOICE_LINE_ID => array_map(fn (Invoice $invoice): int => $invoice->id, $invoices),
        ]);
    }

    /**
     * Builds a message with the given Invoice(lines), applying the corresponding config.
     * The given customer is used as a debtor for the batch.
     * A config instance for each invoice line can be used to customize the line's contents in the message.
     *
     * @param Customer                   $customer               The Customer entity which will be translated to a Debtor.
     * @param Invoice[]                  $invoices               The Invoice(lines) to include in this message.
     * @param InvoiceLineMessageConfig[] $configs                An array containing representations of parameters for each Invoice(line).
     * @param bool                       $createInvoiceInstantly Message results in 1 invoice with the attached invoice lines right away in Harbor.
     *
     * @throws InvoiceLineToHarborException
     */
    public function build(
        Customer $customer,
        array $invoices,
        array $configs = [],
        bool $createInvoiceInstantly = false,
    ): DebtorInvoiceLines {
        return new DebtorInvoiceLines(
            debtor: $this->debtorBuilder->fromCustomer($customer),
            items: new InvoiceLineCollection(
                array_map(fn (Invoice $invoice): InvoiceLine => $this->createInvoiceLineFrom($this->getConfigForInvoice(
                    $invoice,
                    $configs,
                )), $invoices),
            ),
            createInvoiceInstantly: $createInvoiceInstantly,
        );
    }

    /**
     * We *could* just make sure the passed array of configs has the same order as the passed Invoice entities,
     * but that is much more prone to strange errors when this isn't done correctly.
     * This instead makes sure that the retrieved config's Invoice corresponds to the given Invoice.
     *
     * @param InvoiceLineMessageConfig[] $configs
     *
     * @throws InvoiceLineToHarborException
     *
     * @return InvoiceLineMessageConfig The config for the given Invoice entity.
     */
    private function getConfigForInvoice(Invoice $invoice, array $configs): InvoiceLineMessageConfig
    {
        $foundConfigs = array_filter(
            $configs,
            fn (InvoiceLineMessageConfig $config): bool => $config->getInvoice()->id === $invoice->id,
        );
        $config = reset($foundConfigs);

        // If you don't provide a config, a new one is made based on the available information.
        // If one was provided however, that config can be used to override values.
        // e.g. if $invoice->type == 'default' but $config->type == 'voucher', voucher is picked.
        // If you provide a config, you must fill in all the values yourself because "null" is also an override.
        if (! $config instanceof InvoiceLineMessageConfig) {
            $subscription = $invoice->subscription;
            $product = $invoice->product;
            $prepaidReference = $invoice->prepaid_reference ?? $this->invoiceRepository->findPrepaidPayment(
                $invoice->paid,
                $subscription,
            );

            $config = new InvoiceLineMessageConfig(
                invoice: $invoice,
                product: $product,
                subscription: $subscription,
                prepaidReference: $prepaidReference,
            );
        }

        return $config;
    }

    private function createInvoiceLineFrom(InvoiceLineMessageConfig $config): InvoiceLine
    {
        $invoice = $config->getInvoice();
        $product = $config->getProduct();
        $subscription = $config->getSubscription();
        $creditedInvoiceId = $config->getCreditedInvoiceId();

        return new InvoiceLine(
            startDate: $invoice->start_date->format(DateTimeFormat::DATE),
            endDate: $invoice->end_date->format(DateTimeFormat::DATE),
            periodMonths: $invoice->period,
            title: $invoice->title,
            description: $invoice->description,
            purchaseReference: null,
            groupLabel: $invoice->group_label,
            grossPrice: $invoice->gross_price ?? $invoice->net_price,
            netPrice: $invoice->net_price,
            creditedInvoiceId: $creditedInvoiceId,
            ledgerCode: (string) $invoice->ledger_code,
            vatCode: $invoice->vat_code,
            vatRate: $invoice->vat_rate,
            prepaid: $invoice->paid,
            productId: $product->id,
            subscriptionId: $subscription?->id,
            prepaidReference: $config->getPrepaidReference(),
            waterfrontInvoiceId: $invoice->id,
            creditReason: $invoice->credit_reason,
            type: $invoice->type,
            mergeOnPdfWithWaterfrontInvoiceId: $invoice->merge_on_pdf_with_invoice_id,
        );
    }

    /** @param Invoice[] $invoices */
    private function markInvoicesAsSent(array $invoices): void
    {
        $now = CarbonImmutable::now();

        $this->invoiceModifier->bulkUpdate($invoices, [
            'sent_to_harbor_at' => $now,
        ]);
    }
}
