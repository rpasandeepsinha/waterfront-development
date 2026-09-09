<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Helpers;

use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Webmozart\Assert\Assert;

class RenewalHelper
{
    public static function isChildRenewable(Subscription $subscription): bool
    {
        Assert::notNull($subscription->parent_subscription_id, 'Provided subscription is not a child subscription');

        return $subscription->administrative_status === AdministrativeStatus::ACTIVE->value;
    }
}
