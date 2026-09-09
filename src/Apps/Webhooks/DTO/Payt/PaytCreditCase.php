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
        public string|null $resourceType,
        public string|null $creditCaseNumber,
        public string|null $interest,
        public string|null $collectionCosts,
        public string|null $openInterestAndCollectionCosts,
        public string|null $link,
        public string|null $publicLink,
        public array $invoices,
        public PaytDebtor|null $debtor,
        public PaytAdministration|null $administration,
    ) {
        parent::__construct($resourceType);
    }
}
