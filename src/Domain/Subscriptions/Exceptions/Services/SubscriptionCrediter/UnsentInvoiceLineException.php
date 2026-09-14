<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Exceptions\Services\SubscriptionCrediter;

use Waterfront\Domain\Invoices\Models\Invoice;

/**
 * If an invoice line is not present in Harbor, the credit cannot be performed.
 */
class UnsentInvoiceLineException extends SubscriptionCrediterException
{
    public function __construct(Invoice $invoiceLine, string $callerFqcn)
    {
        $this->context = [
            'invoiceLine' => $invoiceLine,
            'callerFqcn' => $callerFqcn,
        ];

        parent::__construct(sprintf(
            'Attempted to credit an invoice line that has yet to be sent to Harbor. ' . 'Invoice line %s.',
            $invoiceLine->id,
        ));
    }
}
