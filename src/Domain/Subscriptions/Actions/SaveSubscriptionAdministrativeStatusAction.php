<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Actions;

use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class SaveSubscriptionAdministrativeStatusAction
{
    public function execute(Subscription $subscription, AdministrativeStatus $status): void
    {
        $subscription->administrative_status = $status->value;
        $subscription->save();
    }
}
