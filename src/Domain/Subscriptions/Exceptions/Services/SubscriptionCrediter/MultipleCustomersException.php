<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Exceptions\Services\SubscriptionCrediter;

use Waterfront\Domain\Harbor\Services\Message\MessageService;
use Waterfront\Domain\Invoices\Models\Invoice;

/**
 * @see MessageService By design, this service only supports the building of messages for one customer at a time.
 *  The same goes for all services that make use of messages, whether it's in Waterfront or Harbor.
 */
class MultipleCustomersException extends SubscriptionCrediterException
{
    public function __construct(Invoice $invoiceLine, int $customerId, string $callerFqcn)
    {
        $this->context = [
            'invoiceLine' => $invoiceLine,
            'customerId' => $customerId,
            'callerFqcn' => $callerFqcn,
        ];

        parent::__construct(sprintf(
            (
                'Attempted to construct a message for multiple customers at a time. '
                . 'Got customer id %d on invoice line id %d, but only invoice lines for customer id %d are expected.'
            ),
            $invoiceLine->customer->id,
            $invoiceLine->id,
            $customerId,
        ));
    }
}
