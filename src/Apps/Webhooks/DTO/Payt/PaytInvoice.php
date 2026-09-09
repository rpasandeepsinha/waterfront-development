<?php

declare(strict_types=1);

namespace Waterfront\Apps\Webhooks\DTO\Payt;

class PaytInvoice extends PaytWebhookContext
{
    public function __construct(
        string|null $resourceType,
        public int $id,
        public string|null $invoiceNumber,
        public string|null $invoiceDate,
        public string|null $dueDate,
        public string|null $amountTotal,
        public string|null $amountOpen,
        public string|null $currencyCode,
        public string|null $orderNumber,
        public PaytDebtor|null $debtor,
        public PaytAdministration|null $administration,
    ) {
        parent::__construct($resourceType);
    }
}
