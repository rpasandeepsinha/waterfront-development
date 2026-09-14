<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\DTO;

use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelReason;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelType;

class RequestCancellationDTO
{
    public function __construct(
        public readonly string $uuid,
        public readonly bool $cancel,
        public readonly SubscriptionCancelType $cancelType,
        public readonly SubscriptionCancelReason $cancelReason,
    ) {
    }
}
