<?php

declare(strict_types=1);

namespace Waterfront\Support\Database;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @implements CastsAttributes<UuidInterface, string>
 */
class UuidCast implements CastsAttributes
{
    /**
     * @param array<mixed> $attributes
     */
    public function get($model, $key, $value, array $attributes): ?UuidInterface
    {
        if (is_string($value)) {
            return Uuid::fromString($value);
        }

        return null;
    }

    /**
     * @param array<mixed> $attributes
     */
    public function set($model, $key, $value, array $attributes)
    {
        /** @var string|UuidInterface $value */
        if ($value instanceof UuidInterface) {
            return $value->toString();
        }

        return $value;
    }
}
