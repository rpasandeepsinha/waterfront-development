<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\General\Traits;

use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Resource;

trait RedirectBackToResourceIndex
{
    /**
     * Return the location to redirect the user after creation.
     */
    public static function redirectAfterCreate(NovaRequest $request, Resource $resource): string
    {
        return '/resources/' . static::uriKey();
    }

    /**
     * Return the location to redirect the user after update.
     */
    public static function redirectAfterUpdate(NovaRequest $request, Resource $resource): string
    {
        return '/resources/' . static::uriKey();
    }
}
