<?php

declare(strict_types=1);

namespace Waterfront\Apps\Webhooks\DTO\Payt;

class PaytDebtor extends PaytWebhookContext
{
    public function __construct(
        string|null $resourceType,
        public int $id,
        public string|null $companyName,
        public string|null $name,
        public string|null $debtorCode,
        public PaytAdministration|null $administration,
    ) {
        parent::__construct($resourceType);
    }
}
