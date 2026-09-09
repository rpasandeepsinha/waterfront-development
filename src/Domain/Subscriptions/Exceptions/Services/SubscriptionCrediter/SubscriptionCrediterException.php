<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Exceptions\Services\SubscriptionCrediter;

use Exception;

/**
 * Opt for exceptions differentiated by classes, for simplified user feedback.
 */
abstract class SubscriptionCrediterException extends Exception
{
    /** @var array<string, mixed> */
    protected array $context;

    /** @return array<string, mixed> */
    public function getContext(): array
    {
        return $this->context;
    }
}
