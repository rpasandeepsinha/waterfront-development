<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Exceptions;

use Exception;

class CancelNotRevertedException extends Exception
{
    public static function subscriptionNotCancelled(int $subscriptionId, string $subscriptionUuid): self
    {
        return new CancelNotRevertedException(
            sprintf(
                'Subscription with ID "%d" and UUID "%s" not reverted because of non cancelled administrative status and/or no existing cancellation date',
                $subscriptionId,
                $subscriptionUuid,
            ),
        );
    }

    public static function subscriptionEndDateExpired(int $subscriptionId, string $subscriptionUuid): self
    {
        return new CancelNotRevertedException(
            sprintf(
                'The Subscription with ID "%d" and UUID "%s" has an expired end_date',
                $subscriptionId,
                $subscriptionUuid,
            ),
        );
    }
}
