<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\VPS\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;

class NovaVirtualMachineDeploymentPolicy
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

    public function attachAnySshKey(): bool
    {
        return false;
    }

    public function attachSshKey(): bool
    {
        return false;
    }

    public function detachSshKey(): bool
    {
        return false;
    }
}
