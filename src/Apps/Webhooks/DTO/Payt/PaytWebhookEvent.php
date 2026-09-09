<?php

declare(strict_types=1);

namespace Waterfront\Apps\Webhooks\DTO\Payt;

use Symfony\Component\Serializer\Attribute\DiscriminatorMap;

#[DiscriminatorMap(typeProperty: 'event_name', mapping: [
    self::INVOICE_NEW_COMMENT => PaytInvoiceCommentEvent::class,
    self::CASE_NEW_COMMENT => PaytInvoiceCommentEvent::class,
    self::DEBTOR_NEW_COMMENT => PaytInvoiceCommentEvent::class,
])]
abstract class PaytWebhookEvent
{
    public const string INVOICE_NEW_COMMENT = 'invoice_new_comment';
    public const string CASE_NEW_COMMENT = 'case_new_comment';
    public const string DEBTOR_NEW_COMMENT = 'debtor_new_comment';

    public function __construct(
        public readonly string $eventName,
        public readonly string $eventTime,
    ) {
    }
}
