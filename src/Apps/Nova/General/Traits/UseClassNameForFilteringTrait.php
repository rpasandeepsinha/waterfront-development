<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\General\Traits;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Laravel\Nova\Http\Requests\NovaRequest;

trait UseClassNameForFilteringTrait
{
    /**
     * Override so we can filter resources to only display certain resources and still use translations
     * for the name field.
     * https://github.com/laravel/nova-issues/issues/241#issuecomment-445583399.
     */
    public static function relatableQuery(NovaRequest $request, Builder $query): Builder
    {
        // Create the filter method name
        $method = 'relatable' . Str::plural(basename(str_replace('\\', '/', static::class))) . 'Filter';
        // Get the called resource instance
        $resource = $request->newResource();
        // Check if the filter method exists
        if (method_exists($resource, $method)) {
            $query = $resource->{$method}($request, $query);
        }

        return parent::relatableQuery($request, $query);
    }
}
