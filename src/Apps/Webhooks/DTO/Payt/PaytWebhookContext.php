<?php

declare(strict_types=1);

namespace Waterfront\Apps\Webhooks\DTO\Payt;

use Symfony\Component\Serializer\Attribute\DiscriminatorMap;

#[DiscriminatorMap(typeProperty: 'resource_type', mapping: [
    'invoice' => PaytInvoice::class,
    'credit_case' => PaytCreditCase::class,
    'debtor' => PaytDebtor::class,
])]
abstract class PaytWebhookContext
{
    public function __construct(
        public ?string $resourceType,
    ) {
    }
}
