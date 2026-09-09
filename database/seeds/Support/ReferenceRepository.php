<?php

declare(strict_types=1);

namespace Database\Seeders\Support;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;
use UnitEnum;

class ReferenceRepository
{
    /** @var array<string, Model> */
    private array $models = [];

    /**
     * @template T of Model
     *
     * @param class-string<T> $expectedClass
     *
     * @throws RuntimeException
     *
     * @return T
     */
    public function get(UnitEnum $reference, string $expectedClass): object
    {
        if (! array_key_exists($reference->name, $this->models)) {
            throw new RuntimeException(sprintf('Reference not found: %s', $reference->name));
        }

        $model = $this->models[$reference->name];

        if (! $model instanceof $expectedClass) {
            throw new RuntimeException(sprintf('Reference is not of expected class: %s', $expectedClass));
        }

        return $model;
    }

    /**
     * @throws RuntimeException
     */
    public function set(UnitEnum $reference, Model $referenceObject): void
    {
        if (array_key_exists($reference->name, $this->models)) {
            throw new RuntimeException(sprintf('Reference already exists: %s', $reference->name));
        }

        $this->models[$reference->name] = $referenceObject;
    }
}
