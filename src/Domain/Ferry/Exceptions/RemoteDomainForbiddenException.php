<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Exceptions;

use RuntimeException;
use Throwable;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class RemoteDomainForbiddenException extends RuntimeException
{
    public function __construct(Subscription $subscription, ProviderSlug $driver, Throwable $previous)
    {
        $message = sprintf(
            'Domain {%s} fetch forbidden in backend {%s} and business unit {%s} for customer with id {%d} error message from remote: %s',
            $subscription->domain,
            $driver->value,
            $subscription->domainDeployment?->businessUnit->slug ?? 'null',
            $subscription->customer_id,
            $previous->getMessage()
        );
        parent::__construct($message, $previous->getCode(), $previous);
    }
}
