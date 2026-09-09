<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Exceptions;

use Exception;
use Waterfront\Domain\AuditLogs\Enums\AuditLogEvent;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class UnableToSuspendSubscriptionException extends Exception
{
    public static function subscriptionAdministrativeOrTechnicalStatusNotSufficient(AuditLogEvent $type, Subscription $subscription): self
    {
        return new self(
            sprintf(
                '%s of subscription with id: %d and domain %s is not possible because the
                 administrative or technical status is wrong. administrative status: %s technical status: %s',
                $type->value,
                $subscription->id,
                $subscription->domain,
                $subscription->administrative_status,
                $subscription->technical_status
            )
        );
    }
}
