<?php

declare(strict_types=1);

namespace Waterfront\Domain\Harbor\Services\Invoice;

use SandwaveIo\HarborMessages\Message\DebtorInvoiceLines;
use SandwaveIo\HarborMessages\Message\Enum\InvoiceLineCreditReason;
use Waterfront\Domain\Harbor\DTO\Message\InvoiceLineMessageConfig;
use Waterfront\Domain\Harbor\DTO\Services\Invoice\InvoiceCrediter\InvoiceToCredit;
use Waterfront\Domain\Harbor\Exceptions\InvoiceLineToHarborException;
use Waterfront\Domain\Harbor\Services\Message\MessageService;
use Waterfront\Domain\Invoices\Models\Invoice;

/**
 * TODO: https://yh-jira.atlassian.net/browse/WATER-3860.
 */
class InvoiceSingleCrediter
{
    public function __construct(
        private readonly InvoiceCrediter $invoiceCrediter,
        private readonly MessageService $messageBuilder,
    ) {
    }

    /**
     * @throws InvoiceLineToHarborException
     *
     * @return array<DebtorInvoiceLines>
     */
    public function creditInvoice(
        Invoice $originalInvoiceLine,
        Invoice $newInvoiceLine,
        ?InvoiceLineCreditReason $creditReason = null,
    ): array {
        $invoiceLineMessages = [];

        if ($newInvoiceLine->customer === null) {
            throw InvoiceLineToHarborException::customerNotFound($newInvoiceLine->id);
        }

        $invoiceLineMessages['newInvoiceLineMessage'] = $this->messageBuilder->build(
            $newInvoiceLine->customer,
            [$newInvoiceLine],
        );

        $creditInvoiceLineResult = $this->invoiceCrediter->credit(new InvoiceToCredit(
            $originalInvoiceLine,
            creditReason: $creditReason,
        ));
        $creditInvoiceLine = $creditInvoiceLineResult->getCreditInvoice();

        if ($creditInvoiceLine->subscription === null) {
            throw InvoiceLineToHarborException::subscriptionNotFoundException($creditInvoiceLine->id);
        }

        $creditInvoiceLineConfig = new InvoiceLineMessageConfig(
            invoice: $creditInvoiceLine,
            product: $creditInvoiceLine->subscription->product,
            subscription: $creditInvoiceLine->subscription,
            creditedInvoiceId: $originalInvoiceLine->id,
        );

        if ($originalInvoiceLine->customer === null) {
            throw InvoiceLineToHarborException::customerNotFound($originalInvoiceLine->id);
        }

        $invoiceLineMessages['creditInvoiceLineMessage'] = $this->messageBuilder->build(
            $originalInvoiceLine->customer,
            [$creditInvoiceLine],
            [
                $creditInvoiceLineConfig,
            ],
        );

        return $invoiceLineMessages;
    }
}
