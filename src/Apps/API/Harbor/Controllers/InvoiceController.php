<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Harbor\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use SandwaveIo\HarborMessages\Message\DebtorInvoiceLines;
use SandwaveIo\HarborMessages\Message\Enum\InvoiceLineCreditReason;
use Waterfront\Apps\API\Harbor\DTO\HarborApiResponse;
use Waterfront\Apps\API\Harbor\DTO\ResponseData\Invoice\CreditInvoiceResponseData;
use Waterfront\Apps\API\Harbor\Requests\CreditInvoicesRequest;
use Waterfront\Domain\Harbor\DTO\Message\InvoiceLineMessageConfig;
use Waterfront\Domain\Harbor\DTO\Services\Invoice\InvoiceBatchCrediter\BatchInvoiceCreditResult;
use Waterfront\Domain\Harbor\DTO\Services\Invoice\InvoiceBatchCrediter\InvoiceToCreditBatch;
use Waterfront\Domain\Harbor\DTO\Services\Invoice\InvoiceCrediter\InvoiceToCredit;
use Waterfront\Domain\Harbor\Exceptions\InvoiceLineToHarborException;
use Waterfront\Domain\Harbor\Services\Invoice\InvoiceBatchCrediter;
use Waterfront\Domain\Harbor\Services\Invoice\InvoiceModifier;
use Waterfront\Domain\Harbor\Services\Message\MessageService;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Webmozart\Assert\Assert;

class InvoiceController
{
    public function __construct(
        private readonly InvoiceBatchCrediter $invoiceBatchCrediter,
        private readonly InvoiceModifier $invoiceModifier,
        private readonly MessageService $messageBuilder,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function credit(CreditInvoicesRequest $request): JsonResponse
    {
        /** @var array<array<string, mixed>> $invoicesToCreditData */
        $invoicesToCreditData = $request->input('invoicesToCredit');

        $this->logger->info(sprintf(
            'Received credit request for %d Invoices from Harbor.',
            count($invoicesToCreditData),
        ));

        $invoiceToCreditBatch = $this->createInvoiceToCreditBatch($invoicesToCreditData);
        $batchInvoiceCreditResult = null;
        $creditInvoicesMessage = null;
        $newInvoicesMessage = null;

        DB::transaction(function () use (
            $invoiceToCreditBatch,
            &$batchInvoiceCreditResult,
            &$creditInvoicesMessage,
            &$newInvoicesMessage,
        ): void {
            $this->logger->info(sprintf(
                'Crediting %d and creating %d new Invoices...',
                $invoiceToCreditBatch->count(),
                count($invoiceToCreditBatch->getInvoicesForNew()),
            ));

            $batchInvoiceCreditResult = $this->invoiceBatchCrediter->batchCredit($invoiceToCreditBatch);

            // We immediately send a response with the created entities back to Harbor,
            // so sent_to_harbor_at must not be null.
            // This prevents them from being sent to Harbor later through the message queue.
            $dateNow = CarbonImmutable::now();

            $this->logger->info(sprintf(
                'Marking newly created invoices as sent to Harbor at %s...',
                $dateNow,
            ));

            $this->invoiceModifier->bulkUpdate(
                array_merge(
                    $batchInvoiceCreditResult->getCreditInvoices(),
                    $batchInvoiceCreditResult->getNewInvoices(),
                ),
                [
                    'sent_to_harbor_at' => $dateNow,
                ],
            );

            $this->logger->info('Building messages...');

            // These messages are required for sending back the response.
            // The creation of this is a relatively big process, so include it in the transaction to be safe.
            $creditInvoicesMessage = $this->createCreditInvoicesMessage($batchInvoiceCreditResult->getCreditInvoices());
            $newInvoicesMessage = count($invoiceToCreditBatch->getInvoicesForNew()) > 0
                ? $this->createNewInvoicesMessage($batchInvoiceCreditResult->getNewInvoices())
                : null;
        });
        // These were handled in the transaction.
        Assert::isInstanceOf($batchInvoiceCreditResult, BatchInvoiceCreditResult::class);
        /** @var DebtorInvoiceLines $creditInvoicesMessage */
        /** @var ?DebtorInvoiceLines $newInvoicesMessage */
        $this->logger->info(sprintf(
            'Finished Crediting %d Invoices, which resulted in %d credit & %d new Invoices.',
            $invoiceToCreditBatch->count(),
            count($batchInvoiceCreditResult->getCreditInvoices()),
            count($batchInvoiceCreditResult->getNewInvoices()),
        ));

        return HarborApiResponse::withData(new CreditInvoiceResponseData(
            $creditInvoicesMessage,
            $newInvoicesMessage,
        ));
    }

    /**
     * Transforms a credit request from Harbor into data that can be used to speak with the InvoiceBatchCrediter.
     *
     * @param array<array<string, mixed>> $invoicesToCreditData
     *
     * @throws InvalidArgumentException
     */
    private function createInvoiceToCreditBatch(array $invoicesToCreditData): InvoiceToCreditBatch
    {
        return new InvoiceToCreditBatch(array_map(
            function (array $invoiceToCreditData): InvoiceToCredit {
                /** @var Invoice $invoice */
                $invoice = Invoice::query()
                    ->where('id', '=', $invoiceToCreditData['waterfrontInvoiceId'])
                    ->firstOrFail();
                /** @var int $amountToCredit */
                $amountToCredit = $invoiceToCreditData['amountToCredit'];
                /** @var bool $shouldCreateNewInvoice */
                $shouldCreateNewInvoice = $invoiceToCreditData['shouldCreateNewInvoice'];
                /** @var ?string $creditReason */
                $creditReason = $invoiceToCreditData['creditReason'] ?? null;

                return new InvoiceToCredit(
                    $invoice,
                    $amountToCredit,
                    $shouldCreateNewInvoice,
                    creditReason: $creditReason !== null ? InvoiceLineCreditReason::from($creditReason) : null,
                );
            },
            $invoicesToCreditData,
        ));
    }

    /**
     * @param Invoice[] $creditInvoices
     *
     * @throws InvoiceLineToHarborException
     */
    private function createCreditInvoicesMessage(array $creditInvoices): DebtorInvoiceLines
    {
        /** @var Invoice $firstCreditInvoice */
        $firstCreditInvoice = reset($creditInvoices);
        $customer = $firstCreditInvoice->customer;

        return $this->messageBuilder->build(
            $customer,
            $creditInvoices,
            array_map(function (Invoice $invoice): InvoiceLineMessageConfig {
                $subscriptionId = $invoice->subscription_id;
                $subscription = $subscriptionId !== null
                    ? $this->subscriptionRepository->findById($subscriptionId)
                    : null;
                $product = $invoice->product;
                $creditedInvoice = $invoice->parentInvoice;

                Assert::notNull($creditedInvoice, sprintf(
                    'Failed to retrieve the original invoice from the credit invoice. Credit invoice ID: %d',
                    $invoice->id,
                ));

                return new InvoiceLineMessageConfig(
                    invoice: $invoice,
                    product: $product,
                    subscription: $subscription,
                    creditedInvoiceId: $creditedInvoice->id,
                );
            }, $creditInvoices),
        );
    }

    /**
     * @param Invoice[] $newInvoices
     *
     * @throws InvoiceLineToHarborException
     */
    private function createNewInvoicesMessage(array $newInvoices): DebtorInvoiceLines
    {
        /** @var Invoice $firstNewInvoice */
        $firstNewInvoice = reset($newInvoices);
        $customer = $firstNewInvoice->customer;

        return $this->messageBuilder->build(
            $customer,
            $newInvoices,
        );
    }
}
