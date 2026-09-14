<?php

declare(strict_types=1);

namespace Waterfront\Domain\Harbor\Services\Message\Handler;

use Psr\Log\LoggerInterface;
use SandwaveIo\HarborMessages\Message\WithdrawInvoicePaymentAnnouncement;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Domain\Invoices\Repositories\InvoiceRepository;
use Waterfront\Support\Enums\LoggingContextKeys;

class WithdrawInvoicePaymentAnnouncementHandler
{
    public function __construct(
        private readonly InvoiceRepository $invoiceRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(WithdrawInvoicePaymentAnnouncement $withdrawMessage): void
    {
        foreach ($withdrawMessage->getInvoiceLineIds() as $invoiceLineId) {
            $invoice = $this->invoiceRepository->findById($invoiceLineId);

            if (! $invoice instanceof Invoice) {
                $this->logger->error(
                    'Unknown invoice id: {invoice_line.wf_id}',
                    [
                        LoggingContextKeys::INVOICE_LINE_ID => $invoiceLineId,
                    ],
                );
                continue;
            }

            if ($invoice->isAnnounced() === false) {
                $this->logger->debug(
                    'Invoice was already not announced',
                    [
                        LoggingContextKeys::INVOICE_LINE_ID => $invoice,
                    ],
                );
                continue;
            }

            $invoice->announced_by_harbor_at = null;
            $invoice->save();

            $this->logger->info(
                'Invoice payment announcement withdrawn',
                [
                    LoggingContextKeys::INVOICE_LINE_ID => $invoice->id,
                ],
            );
        }
    }
}
