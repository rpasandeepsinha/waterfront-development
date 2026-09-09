<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Subscriptions\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;

class NovaSubscriptionPolicy
{
    use HandlesAuthorization;

    public function view(): bool
    {
        return true;
    }

    public function create(): bool
    {
        return false;
    }

    public function update(): bool
    {
        return true;
    }

    public function delete(): bool
    {
        return false;
    }

    public function restore(): bool
    {
        return true;
    }

    public function forceDelete(): bool
    {
        return false;
    }

    public function cancel(): bool
    {
        return true;
    }

    public function revert(): bool
    {
        return true;
    }

    public function attachAnyTransfer(): bool
    {
        return false;
    }

    public function attachTransfer(): bool
    {
        return false;
    }

    public function detachTransfer(): bool
    {
        return false;
    }
}
