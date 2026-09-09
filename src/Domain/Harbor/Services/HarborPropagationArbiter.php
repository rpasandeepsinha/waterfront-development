<?php

declare(strict_types=1);

namespace Waterfront\Domain\Harbor\Services;

use Psr\Log\LoggerInterface;
use Waterfront\Domain\Ferry\Repositories\MigrationCustomerRepository;
use Waterfront\Domain\Invoices\Models\Invoice;

readonly class HarborPropagationArbiter
{
    public function __construct(
        private MigrationCustomerRepository $migrationCustomerRepository,
        private LoggerInterface $logger,
    ) {
    }

    public function allowedToPropagate(Invoice $invoice): ArbiterResult
    {
        if ($invoice->sent_to_harbor_at !== null) {
            $message = sprintf(
                'Skipping, invoice has already been sent to Harbor, invoice ID: %s',
                $invoice->id,
            );
            $this->logger->warning($message);
            return new ArbiterResult(false, $message);
        }

        if ($invoice->customer->anonymized_at !== null) {
            $message = sprintf(
                'Tried to propagate an invoice for an anonymized customer: %s , invoice ID: %s ',
                $invoice->customer->customer_number,
                $invoice->id
            );
            $this->logger->emergency($message);
            return new ArbiterResult(false, $message);
        }

        // Always let these pass, since the Harbor bank import expects them to be present.
        if ($invoice->paid) {
            return new ArbiterResult(true, 'Invoice is prepaid!');
        }

        if ($this->migrationCustomerRepository->isCustomerInActiveMigrationWithInvoicingDisabled($invoice->customer_id)) {
            $message = sprintf(
                'Invoicing is disabled for customer: %s, invoice ID: %s',
                $invoice->customer->id,
                $invoice->id
            );
            $this->logger->debug($message);
            return new ArbiterResult(false, $message);
        }

        return new ArbiterResult(true, null);
    }
}
