<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\DTO;

class SubscriptionChangeResult
{
    public const string STATUS_OK = 'ok';

    public const string STATUS_ERROR = 'error';

    public function __construct(
        public readonly string $status,
        public readonly ?int $errorCode = null,
        public readonly ?string $errorMessage = null,
    ) {
    }
}
