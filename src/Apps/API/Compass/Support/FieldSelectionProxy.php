<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Proxy that controls field-level access for both query building (SELECT) and response serialization.
 *
 * - Parses `fields[]` from the request once on construction.
 * - `resolve()` — builds the serialized array for a Resource, calling only requested field resolvers.
 * - `applyTo()` — applies a SELECT restriction to an Eloquent query builder.
 *
 * @template TModel of Model
 */
class FieldSelectionProxy
{
    /** @var string[] */
    private readonly array $requestedFields;

    /**
     * @param array<string, FieldDefinition<TModel>> $definitions Maps frontend field name => FieldDefinition.
     */
    public function __construct(
        private readonly Request $request,
        private readonly array $definitions,
    ) {
        $this->requestedFields = array_values(
            array_filter((array) $request->input('fields', []), 'is_string'),
        );
    }

    /**
     * Returns true when the given field was requested (or when no `fields[]` restriction is active).
     */
    public function isRequested(string $field): bool
    {
        return $this->requestedFields === [] || in_array($field, $this->requestedFields, true);
    }

    /**
     * Resolves all requested fields against the given model instance.
     *
     * @param TModel $model
     *
     * @return array<string, mixed>
     */
    public function resolve(Model $model): array
    {
        $result = [];

        foreach ($this->definitions as $field => $definition) {
            if ($this->isRequested($field)) {
                $result[$field] = ($definition->resolve)($model);
            }
        }

        return $result;
    }

    /**
     * Applies a SELECT restriction to the query, limiting to always-required columns plus any
     * DB-backed fields that were requested, filtered on, or sorted by.
     * Fields with `column = null` (relations / computed) are skipped.
     *
     * Call this **last** in your filter's `apply()` so it overrides any earlier wildcard SELECT.
     *
     * @param Builder<TModel> $query
     * @param TModel          $model          Used to resolve the table name and valid schema columns.
     * @param string[]        $alwaysRequired Bare column names always included regardless of `fields[]`.
     */
    public function applyTo(Builder $query, Model $model, array $alwaysRequired): void
    {
        if ($this->requestedFields === []) {
            return;
        }

        $table = $model->getTable();
        /** @var string[] $existingColumns */
        $existingColumns = Schema::getColumnListing($table);

        // Always-required columns (qualified)
        $select = array_values(array_filter(
            array_map(fn (string $col) => in_array($col, $existingColumns, true)
                ? "{$table}.{$col}"
                : null, $alwaysRequired),
        ));

        // Collect all field names that need a column, then deduplicate before resolving
        $orderBy = array_filter((array) $this->request->input('orderBy', []), 'is_string');
        $sortFields = array_map(fn (string $o) => (string) preg_replace('/_(asc|desc)$/i', '', $o), $orderBy);

        $filterFields = array_keys(array_filter(
            array_fill_keys(array_keys($this->definitions), null),
            fn (mixed $_, string $field) => $this->request->filled($field),
            ARRAY_FILTER_USE_BOTH,
        ));

        $fields = array_unique([...$this->requestedFields, ...$filterFields, ...$sortFields]);

        foreach ($fields as $field) {
            $definition = $this->definitions[$field] ?? null;

            if ($definition?->column !== null && in_array($definition->column, $existingColumns, true)) {
                $select[] = "{$table}.{$definition->column}";
            }
        }

        $query->select($select);
    }
}
