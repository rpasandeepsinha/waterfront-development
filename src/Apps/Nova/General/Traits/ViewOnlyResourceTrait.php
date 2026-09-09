<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\General\Traits;

use Illuminate\Http\Request;

trait ViewOnlyResourceTrait
{
    public function authorizedToUpdate(Request $request): bool
    {
        return false;
    }

    public function authorizedToForceUpdate(Request $request): bool
    {
        return false;
    }

    public function authorizedToDelete(Request $request): bool
    {
        return false;
    }

    public function authorizedToForceDelete(Request $request): bool
    {
        return false;
    }

    public function authorizedToDeleteForSerialization(Request $request): bool
    {
        return false;
    }

    public static function authorizedToCreate(Request $request): bool
    {
        return false;
    }
}
