<?php

declare(strict_types=1);

namespace Waterfront\Apps\Webhooks\DTO\Payt;

class PaytCreditCase extends PaytWebhookContext
{
    /**
     * @param PaytInvoice[] $invoices
     */
    public function __construct(
        public int $id,
        public ?string $resourceType,
        public ?string $creditCaseNumber,
        public ?string $interest,
        public ?string $collectionCosts,
        public ?string $openInterestAndCollectionCosts,
        public ?string $link,
        public ?string $publicLink,
        public array $invoices,
        public ?PaytDebtor $debtor,
        public ?PaytAdministration $administration,
    ) {
        parent::__construct($resourceType);
    }
}
