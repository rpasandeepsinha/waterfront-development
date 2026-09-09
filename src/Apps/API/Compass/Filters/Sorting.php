<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Sorting
{
    /**
     * @template TModel of Model
     *
     * @param Builder<TModel> $query
     * @param string[]        $orderBy
     */
    public function apply(Builder $query, array $orderBy): void
    {
        foreach ($orderBy as $order) {
            $lastUnderscore = strrpos($order, '_');

            if ($lastUnderscore === false) {
                continue;
            }

            $column = substr($order, 0, $lastUnderscore);
            $direction = substr($order, $lastUnderscore + 1);

            if (! in_array($direction, ['asc', 'desc'], true)) {
                continue;
            }

            $query->orderBy($column, $direction);
        }
    }
}
