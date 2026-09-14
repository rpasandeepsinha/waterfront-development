<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Exceptions\Services\SubscriptionCrediter;

use Waterfront\Domain\Harbor\Services\Message\MessageService;
use Waterfront\Domain\Invoices\Models\Invoice;

/**
 * @see MessageService In the case of sending over a credit invoice line towards Harbor,
 *  it's extremely important that this message has the field "credited_invoice_line" filled in.
 *  Harbor uses this field to determine which invoice line in Harbor's system was credited with that received line,
 *  so that it can create a reference between the eventual credit and existing debit invoice.
 */
class MissingParentInvoiceLineException extends SubscriptionCrediterException
{
    public function __construct(Invoice $invoiceLine, string $callerFqcn)
    {
        $this->context = [
            'invoiceLine' => $invoiceLine,
            'callerFqcn' => $callerFqcn,
        ];

        parent::__construct(sprintf(
            'Attempted to construct a message with an invoice line that has no attached parent invoice line. '
            . 'Invoice line %d.',
            $invoiceLine->id,
        ));
    }
}
