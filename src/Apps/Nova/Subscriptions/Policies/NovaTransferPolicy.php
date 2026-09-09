<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Subscriptions\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;

class NovaTransferPolicy
{
    use HandlesAuthorization;

    public function attachAnySubscription(): bool
    {
        return false;
    }

    public function attachSubscription(): bool
    {
        return false;
    }

    public function detachSubscription(): bool
    {
        return false;
    }
}
