<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Products\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;

class NovaProductPolicy
{
    use HandlesAuthorization;

    public function view(): bool
    {
        return true;
    }

    public function create(): bool
    {
        return true;
    }

    public function update(): bool
    {
        return true;
    }

    public function delete(): bool
    {
        return true;
    }

    public function restore(): bool
    {
        return true;
    }

    public function forceDelete(): bool
    {
        return true;
    }
}
