<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Exceptions;

use Exception;

class SubscriptionChangeException extends Exception
{
    public static function noPotentialProducts(string $subscriptionUuid, string $productName): self
    {
        return new self(sprintf(
            'Unable to change subscription with UUID "%s" to product "%s". No potential up- or downgrades found.',
            $subscriptionUuid,
            $productName,
        ));
    }

    public static function noProlongationProductPrice(
        string $productName,
        int $billingPeriod,
        int $contractPeriod,
    ): self {
        return new self(sprintf(
            'No prolongation product price available for product "%s", billing period "%d" and contract period "%d".',
            $productName,
            $billingPeriod,
            $contractPeriod,
        ));
    }
}
