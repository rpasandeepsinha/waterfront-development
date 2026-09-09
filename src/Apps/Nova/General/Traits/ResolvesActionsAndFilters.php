<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\General\Traits;

use Illuminate\Support\Facades\App;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Filters\Filter;

/**
 * Resolves Actions/Filters and makes them singleton if they're not already.
 *
 * Making them singleton speeds up page load quite a bit.
 *
 * @see https://github.com/laravel/nova-issues/issues/2461
 */
trait ResolvesActionsAndFilters
{
    /**
     * @param class-string<Action> $className
     */
    private function resolveAction(string $className): Action
    {
        App::singletonIf($className);

        /** @var Action $action */
        $action = App::make($className);

        return $action;
    }

    /**
     * @param class-string<Filter> $className
     */
    private function resolveFilter(string $className): Filter
    {
        App::singletonIf($className);

        /** @var Filter $filter */
        $filter = App::make($className);

        return $filter;
    }
}
