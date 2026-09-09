<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Actions;

use Carbon\CarbonImmutable;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class SaveSubscriptionTerminationDateAction
{
    public function execute(Subscription $subscription, CarbonImmutable $terminationDate): void
    {
        $subscription->termination_date = $terminationDate;
        $subscription->save();
    }
}
