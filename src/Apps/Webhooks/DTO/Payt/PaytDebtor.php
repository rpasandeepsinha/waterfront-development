<?php

declare(strict_types=1);

namespace Waterfront\Apps\Webhooks\DTO\Payt;

class PaytDebtor extends PaytWebhookContext
{
    public function __construct(
        ?string $resourceType,
        public int $id,
        public ?string $companyName,
        public ?string $name,
        public ?string $debtorCode,
        public ?PaytAdministration $administration,
    ) {
        parent::__construct($resourceType);
    }
}
