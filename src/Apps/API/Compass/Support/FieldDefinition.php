<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Support;

use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * Describes a single selectable field: the DB column it requires and how to resolve its serialized value.
 *
 * @template TModel of Model
 */
class FieldDefinition
{
    /**
     * @param string|null           $column  DB column name on the model's table. Null for relations / computed fields.
     * @param Closure(TModel):mixed $resolve Callable that receives the model and returns the serialized value.
     */
    public function __construct(
        public readonly ?string $column,
        public readonly Closure $resolve,
    ) {
    }
}
