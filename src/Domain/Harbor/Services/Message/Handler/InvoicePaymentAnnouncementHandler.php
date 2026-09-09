<?php

declare(strict_types=1);

namespace Waterfront\Domain\Harbor\Services\Message\Handler;

use Carbon\CarbonImmutable;
use Psr\Log\LoggerInterface;
use SandwaveIo\HarborMessages\Message\InvoicePaymentAnnouncement;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Domain\Invoices\Repositories\InvoiceRepository;
use Waterfront\Support\Enums\LoggingContextKeys;

class InvoicePaymentAnnouncementHandler
{
    public function __construct(
        private readonly InvoiceRepository $invoiceRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(InvoicePaymentAnnouncement $announcementMessage): void
    {
        foreach ($announcementMessage->getInvoiceLineIds() as $invoiceLineId) {
            $invoice = $this->invoiceRepository->findById($invoiceLineId);

            if (! $invoice instanceof Invoice) {
                $this->logger->error(
                    'Unknown invoice id: {invoice_line.wf_id}',
                    [
                     LoggingContextKeys::INVOICE_LINE_ID => $invoiceLineId,
                    ]
                );
                continue;
            }

            if ($invoice->isAnnounced()) {
                $this->logger->debug(
                    'Invoice was already announced',
                    [
                        LoggingContextKeys::INVOICE_LINE_ID => $invoice,
                    ]
                );
                continue;
            }

            $announced = CarbonImmutable::createFromTimestamp($announcementMessage->getPaymentAnnounced(), date_default_timezone_get());
            $invoice->announced_by_harbor_at = $announced;
            $invoice->save();

            $this->logger->info(
                'Invoice payment announcement set',
                [
                    LoggingContextKeys::INVOICE_LINE_ID => $invoice->id,
                ]
            );
        }
    }
}
