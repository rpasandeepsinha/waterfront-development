<?php

declare(strict_types=1);

namespace Waterfront\Apps\Webhooks\DTO\Payt;

class PaytInvoice extends PaytWebhookContext
{
    public function __construct(
        ?string $resourceType,
        public int $id,
        public ?string $invoiceNumber,
        public ?string $invoiceDate,
        public ?string $dueDate,
        public ?string $amountTotal,
        public ?string $amountOpen,
        public ?string $currencyCode,
        public ?string $orderNumber,
        public ?PaytDebtor $debtor,
        public ?PaytAdministration $administration,
    ) {
        parent::__construct($resourceType);
    }
}
