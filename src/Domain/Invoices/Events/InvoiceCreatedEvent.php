<?php

declare(strict_types=1);

namespace Waterfront\Domain\Invoices\Events;

use Waterfront\Domain\Invoices\Models\Invoice;

class InvoiceCreatedEvent
{
    public function __construct(
        public Invoice $invoice,
        public bool $isRenewed
    ) {
    }
}
