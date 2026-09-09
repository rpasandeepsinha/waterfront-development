<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Exceptions\Services\SubscriptionCrediter;

use Waterfront\Domain\Subscriptions\Models\Subscription;

class MissingInvoiceLinesException extends SubscriptionCrediterException
{
    /**
     * @param Subscription[] $subscriptions
     */
    public function __construct(array $subscriptions, string $callerFqcn)
    {
        $this->context = [
            'subscriptions' => $subscriptions,
            'callerFqcn' => $callerFqcn,
        ];

        parent::__construct(sprintf(
            (
                'Attempted to credit a set of subscriptions that have no debit invoice lines. '
                . 'Got these subscription ids: <%s>'
            ),
            implode(', ', array_map(fn (Subscription $subscription): int => $subscription->id, $subscriptions)),
        ));
    }
}
